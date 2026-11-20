<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Invoice\Service;

use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use finfo;
use LukaLtaApi\Api\Business\Service\BusinessCustomerLookup;
use LukaLtaApi\Api\Business\Service\BusinessDocumentStorage;
use LukaLtaApi\Api\Business\Service\PendingPdfUploads;
use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Exception\BusinessDocumentStateException;
use LukaLtaApi\Exception\BusinessInvoiceNotFoundException;
use LukaLtaApi\Repository\BusinessInvoiceRepository;
use LukaLtaApi\Repository\BusinessNumberSequenceRepository;
use LukaLtaApi\Repository\BusinessSettingsRepository;
use LukaLtaApi\Value\Business\BusinessClock;
use LukaLtaApi\Value\Business\DocumentFieldParser;
use LukaLtaApi\Value\Business\DocumentNumber;
use LukaLtaApi\Value\Business\DocumentPrefix;
use LukaLtaApi\Value\Business\Invoice;
use LukaLtaApi\Value\Business\InvoiceStatus;
use LukaLtaApi\Value\Business\Money;
use LukaLtaApi\Value\Business\PdfFile;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

/**
 * Import einer außerhalb des Systems ausgestellten Rechnung samt Original-PDF. Die Nummer wird manuell
 * übernommen; liegt sie über dem Zählerstand ihres Jahres, wird der RE-Kreis darauf angehoben, damit die
 * Nummerierung lückenlos weiterläuft.
 */
class InvoiceImportService
{
    /** 10 MiB in Bytes */
    private const int MAX_PDF_SIZE = 10 * 1024 * 1024;

    private const string PDF_MIME_TYPE = 'application/pdf';

    private const string PDF_KEY_PREFIX = 'business/invoices/';

    /** Obergrenze von total_cents (signed INT in MySQL) */
    private const int MAX_TOTAL_CENTS = 2147483647;

    /** Obergrenze für das Zahlungsziel in Tagen (wie bei Entwürfen) */
    private const int MAX_PAYMENT_TERM_DAYS = 365;

    private const string DATE_FORMAT = DocumentFieldParser::DATE_FORMAT;

    public function __construct(
        private readonly BusinessInvoiceRepository $repository,
        private readonly BusinessNumberSequenceRepository $sequenceRepository,
        private readonly BusinessSettingsRepository $settingsRepository,
        private readonly BusinessCustomerLookup $customerLookup,
        private readonly BusinessDocumentStorage $documentStorage,
    ) {
    }

    /** @param array<string, mixed> $data Multipart-Felder; leere Felder gelten als nicht gesendet */
    public function importInvoice(array $data, ?UploadedFileInterface $file): ApiResult
    {
        $data = array_filter($data, static fn(mixed $value): bool => $value !== '' && $value !== null);
        $today = BusinessClock::today()->format(self::DATE_FORMAT);

        $invoiceDate = $this->requireDate($data, 'invoiceDate');
        if ($invoiceDate->format(self::DATE_FORMAT) > $today) {
            throw new ApiValidationException('invoiceDate must not be in the future', 400);
        }
        $invoiceNumber = $this->parseInvoiceNumber($data['invoiceNumber'] ?? null, $invoiceDate);
        $status = $this->parseStatus($data['status'] ?? null);
        $total = $this->parseTotal($data['totalCents'] ?? null);
        $serviceDate = DocumentFieldParser::optionalDate($data, 'serviceDate');
        $dueDate = DocumentFieldParser::optionalDate($data, 'dueDate');
        $paidAt = $this->parsePaidAt($data, $status, $invoiceDate, $today);
        if ($status === InvoiceStatus::Open && $dueDate === null) {
            throw new ApiValidationException('dueDate is required for open invoices', 400);
        }
        $pdf = $this->readPdf($file, $invoiceNumber);

        // Archivierte Kunden sind erlaubt: eine Altrechnung kann an einen früheren Kunden gegangen sein
        $customer = $this->customerLookup->loadCustomerOrFail($this->customerLookup->parseCustomerId($data));
        $paymentTermDays = $dueDate !== null
            ? $this->paymentTermDaysUntil($invoiceDate, $dueDate)
            : ($this->settingsRepository->loadSettings()?->getDefaultPaymentTermDays()
                ?? Invoice::FALLBACK_PAYMENT_TERM_DAYS);
        $dueDate ??= $invoiceDate->modify('+' . $paymentTermDays . ' days');

        $invoice = Invoice::imported(
            $customer,
            $invoiceNumber,
            $status,
            $invoiceDate,
            $serviceDate,
            $paymentTermDays,
            $dueDate,
            $total,
            $paidAt,
        );

        $uploads = new PendingPdfUploads();
        try {
            $this->repository->executeInTransaction(
                fn() => $this->storeImport($invoice, $invoiceNumber, $pdf, $uploads),
            );
        } catch (Throwable $exception) {
            // Greift auch, wenn erst der Commit scheitert
            $this->documentStorage->discardPendingUploads($uploads);

            throw $exception;
        }

        $stored = $this->repository->loadInvoice($invoice->getInvoiceId());
        if ($stored === null) {
            throw new BusinessInvoiceNotFoundException();
        }

        return ApiResult::from(
            JsonResult::from('Invoice imported', ['invoice' => $stored->toArray()]),
            StatusCodeInterface::STATUS_CREATED,
        );
    }

    /**
     * Läuft in der offenen Transaktion. Sperrt zuerst die Sequenzzeile des Jahres: Festschreiben und Import
     * desselben Jahres laufen dadurch nacheinander, die Eindeutigkeitsprüfung danach sieht jede vergebene Nummer.
     */
    private function storeImport(
        Invoice $invoice,
        DocumentNumber $invoiceNumber,
        PdfFile $pdf,
        PendingPdfUploads $uploads,
    ): void {
        $this->sequenceRepository->lockSequence(DocumentPrefix::Invoice, $invoiceNumber->getYear());
        if ($this->repository->isInvoiceNumberTaken($invoiceNumber)) {
            throw BusinessDocumentStateException::numberTaken($invoiceNumber->asString());
        }

        $this->repository->createImported($invoice);

        $invoiceId = $invoice->getInvoiceId();
        $pdfObjectKey = $this->documentStorage->createPdfObjectKey(self::PDF_KEY_PREFIX, $invoiceId->asString());
        $this->documentStorage->storePdf($pdfObjectKey, $pdf->getBody());
        $uploads->record($pdfObjectKey);
        $this->repository->setPdfObjectKey($invoiceId, $pdfObjectKey);

        $this->sequenceRepository->raiseTo(
            DocumentPrefix::Invoice,
            $invoiceNumber->getYear(),
            $invoiceNumber->getSequence(),
        );
    }

    /** Format RE-JJJJ-NNNN in kanonischer Schreibweise; das Jahr muss dem Rechnungsdatum entsprechen */
    private function parseInvoiceNumber(mixed $value, DateTimeImmutable $invoiceDate): DocumentNumber
    {
        if (!is_string($value)) {
            throw new ApiValidationException('invoiceNumber is required', 400);
        }

        $invoiceNumber = DocumentNumber::parse(trim($value), DocumentPrefix::Invoice);
        // Z.B. "RE-2026-00003" wäre sonst als RE-2026-0003 gespeichert, abweichend vom Original-PDF
        if ($invoiceNumber->asString() !== trim($value)) {
            throw new ApiValidationException('invoiceNumber must not have superfluous leading zeros', 400);
        }
        if ($invoiceNumber->getYear() !== (int) $invoiceDate->format('Y')) {
            throw new ApiValidationException('invoiceNumber year must match the year of invoiceDate', 400);
        }

        return $invoiceNumber;
    }

    private function parseStatus(mixed $value): InvoiceStatus
    {
        $status = is_string($value) ? InvoiceStatus::tryFrom($value) : null;
        if ($status !== InvoiceStatus::Open && $status !== InvoiceStatus::Paid) {
            throw new ApiValidationException('status must be open or paid', 400);
        }

        return $status;
    }

    /** Positive Ganzzahl in Cent; aus Multipart kommt sie als Ziffernfolge */
    private function parseTotal(mixed $value): Money
    {
        if (is_string($value) && preg_match('/^[1-9]\d{0,9}$/', $value) === 1) {
            $value = (int) $value;
        }
        if (!is_int($value) || $value <= 0 || $value > self::MAX_TOTAL_CENTS) {
            throw new ApiValidationException('totalCents must be a positive integer', 400);
        }

        return Money::fromCents($value);
    }

    /**
     * paid: Zahlungsdatum Pflicht, nicht vor dem Rechnungsdatum und nicht in der Zukunft; open: nicht erlaubt.
     *
     * @param array<string, mixed> $data
     */
    private function parsePaidAt(
        array $data,
        InvoiceStatus $status,
        DateTimeImmutable $invoiceDate,
        string $today,
    ): ?DateTimeImmutable {
        if ($status === InvoiceStatus::Open && array_key_exists('paidAt', $data)) {
            throw new ApiValidationException('paidAt is only allowed for paid invoices', 400);
        }
        if ($status === InvoiceStatus::Open) {
            return null;
        }

        $paidAt = $this->requireDate($data, 'paidAt');
        $paidAtDay = $paidAt->format(self::DATE_FORMAT);
        if ($paidAtDay < $invoiceDate->format(self::DATE_FORMAT)) {
            throw new ApiValidationException('paidAt must not be before invoiceDate', 400);
        }
        if ($paidAtDay > $today) {
            throw new ApiValidationException('paidAt must not be in the future', 400);
        }

        return $paidAt;
    }

    /** Beide Daten sind Kalendertage um 00:00 derselben Zeitzone, die Differenz also ganze Tage */
    private function paymentTermDaysUntil(DateTimeImmutable $invoiceDate, DateTimeImmutable $dueDate): int
    {
        if ($dueDate->format(self::DATE_FORMAT) < $invoiceDate->format(self::DATE_FORMAT)) {
            throw new ApiValidationException('dueDate must not be before invoiceDate', 400);
        }

        $days = (int) $invoiceDate->diff($dueDate)->days;
        if ($days > self::MAX_PAYMENT_TERM_DAYS) {
            throw new ApiValidationException(
                sprintf('dueDate must be at most %d days after invoiceDate', self::MAX_PAYMENT_TERM_DAYS),
                400,
            );
        }

        return $days;
    }

    /** @param array<string, mixed> $data */
    private function requireDate(array $data, string $key): DateTimeImmutable
    {
        $date = DocumentFieldParser::optionalDate($data, $key);
        if ($date === null) {
            throw new ApiValidationException(sprintf('%s is required', $key), 400);
        }

        return $date;
    }

    /**
     * Größe vor dem Einlesen prüfen; Typ per Inhalt (finfo), nicht per Client-Angabe. Als PdfFile verpackt,
     * damit die Bytes nicht als String-Argument in Stacktraces (Fehlerausgabe in der Entwicklung) landen.
     */
    private function readPdf(?UploadedFileInterface $file, DocumentNumber $invoiceNumber): PdfFile
    {
        if ($file === null) {
            throw new ApiValidationException('Form field file is required', 400);
        }
        $error = $file->getError();
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new ApiValidationException('File size exceeds the maximum limit of 10 MiB', 400);
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new ApiValidationException('File upload failed with error code ' . $error, 400);
        }

        $size = $file->getSize();
        if ($size !== null && $size > self::MAX_PDF_SIZE) {
            throw new ApiValidationException('File size exceeds the maximum limit of 10 MiB', 400);
        }

        $body = $file->getStream()->getContents();
        if ($body === '') {
            throw new ApiValidationException('Uploaded file is empty', 400);
        }
        if (strlen($body) > self::MAX_PDF_SIZE) {
            throw new ApiValidationException('File size exceeds the maximum limit of 10 MiB', 400);
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer($body);
        if ($mimeType !== self::PDF_MIME_TYPE) {
            throw new ApiValidationException('Invalid file type. Only PDF is allowed', 400);
        }

        return PdfFile::from($invoiceNumber->asString() . '.pdf', $body);
    }
}
