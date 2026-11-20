<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Invoice\Service;

use LukaLtaApi\Api\Business\Pdf\BusinessPdfRenderer;
use LukaLtaApi\Api\Business\Service\BusinessCustomerLookup;
use LukaLtaApi\Api\Business\Service\BusinessDocumentStorage;
use LukaLtaApi\Api\Business\Service\PendingPdfUploads;
use LukaLtaApi\Api\BusinessSettings\Service\BusinessSettingsService;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Exception\BusinessDocumentStateException;
use LukaLtaApi\Exception\BusinessInvoiceNotFoundException;
use LukaLtaApi\Repository\BusinessInvoiceRepository;
use LukaLtaApi\Repository\BusinessNumberSequenceRepository;
use LukaLtaApi\Value\Business\DocumentPrefix;
use LukaLtaApi\Value\Business\Invoice;
use LukaLtaApi\Value\Business\InvoiceId;
use LukaLtaApi\Value\Business\InvoiceStatus;
use LukaLtaApi\Value\Business\PostalAddressSnapshot;
use Throwable;

/**
 * Festschreiben einer Rechnung jeder Art (normal, Anzahlung, Schluss, Storno): Nummer aus dem RE-Kreis,
 * Fälligkeit, Snapshots von Kunde und Absender, PDF im privaten Speicher, Status open (Storno: issued).
 *
 * Alles läuft in einer Transaktion auf der gemeinsamen PDO-Verbindung. Wird sie zurückgerollt, ist die Nummer
 * nicht verbraucht; ein bereits hochgeladenes PDF muss dann wieder gelöscht werden (siehe die beiden Einstiege).
 */
class InvoiceFinalizer
{
    private const string PDF_KEY_PREFIX = 'business/invoices/';
    private const string DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private readonly BusinessInvoiceRepository $repository,
        private readonly BusinessNumberSequenceRepository $sequenceRepository,
        private readonly BusinessCustomerLookup $customerLookup,
        private readonly BusinessSettingsService $settingsService,
        private readonly BusinessDocumentStorage $documentStorage,
        private readonly BusinessPdfRenderer $pdfRenderer,
        private readonly InvoicePdfDocumentFactory $pdfDocumentFactory,
    ) {
    }

    /** Öffnet eine eigene Transaktion und räumt bei jedem Fehler (auch beim Commit) das hochgeladene PDF weg */
    public function finalize(InvoiceId $invoiceId): Invoice
    {
        // Schnelle Vorprüfungen ohne Sperre; maßgeblich ist die Prüfung unter Sperre in der Transaktion
        $this->settingsService->loadCompleteOrFail();
        $this->assertDraft($this->repository->loadInvoice($invoiceId)?->getStatus());

        $uploads = new PendingPdfUploads();
        try {
            return $this->repository->executeInTransaction(
                fn(): Invoice => $this->finalizeInOpenTransaction($invoiceId, $uploads),
            );
        } catch (Throwable $exception) {
            $this->documentStorage->discardPendingUploads($uploads);

            throw $exception;
        }
    }

    /**
     * Für Aufrufer, die mehrere Schritte atomar klammern (z.B. Storno anlegen + festschreiben + Original
     * stornieren). Öffnet keine Transaktion, verlangt aber eine offene.
     *
     * Vertrag: der Aufrufer übergibt einen PendingPdfUploads-Sammler, in den das hochgeladene PDF eingetragen
     * wird, sobald es im Speicher liegt. Scheitert die äußere Transaktion danach (auch erst beim Commit), muss
     * der Aufrufer BusinessDocumentStorage::discardPendingUploads($uploads) aufrufen. Der Rückgabewert ist aus
     * der noch offenen Transaktion gelesen.
     */
    public function finalizeInOpenTransaction(InvoiceId $invoiceId, PendingPdfUploads $uploads): Invoice
    {
        if (!$this->repository->isInTransaction()) {
            throw new ApiDatabaseException('Finalizing an invoice requires an open transaction');
        }

        $this->assertDraft($this->repository->lockInvoiceStatus($invoiceId));
        // Erst nach der Sperre laden: ein paralleles PATCH kann nicht mehr dazwischen committen,
        // PDF, Snapshot und Nummernjahr entsprechen also genau der festgeschriebenen Zeile
        $invoice = $this->loadInvoiceOrFail($invoiceId);
        $this->assertServiceDate($invoice);

        $settings = $this->settingsService->loadCompleteOrFail();
        $customer = $this->customerLookup->loadCustomerOrFail($invoice->getCustomerId());

        $year = (int) $invoice->getInvoiceDate()->format('Y');
        $invoiceNumber = $this->sequenceRepository->allocateNext(DocumentPrefix::Invoice, $year);
        $dueDate = $invoice->getDueDate();
        $customerSnapshot = PostalAddressSnapshot::fromCustomer($customer);
        $issuerSnapshot = $settings->toSnapshot();

        $pdf = $this->pdfRenderer->render($this->pdfDocumentFactory->create(
            $invoice,
            $invoiceNumber,
            $dueDate,
            $customerSnapshot,
            $issuerSnapshot,
            $this->documentStorage->loadLogoBytes($settings),
        ));
        $pdfObjectKey = $this->documentStorage->createPdfObjectKey(self::PDF_KEY_PREFIX, $invoiceId->asString());
        $this->documentStorage->storePdf($pdfObjectKey, $pdf);
        $uploads->record($pdfObjectKey);

        $this->repository->markFinalized(
            $invoiceId,
            $invoiceNumber,
            $dueDate->format(self::DATE_FORMAT),
            $customerSnapshot->toArray(),
            $issuerSnapshot,
            $pdfObjectKey,
            $invoice->getKind()->getFinalizedStatus(),
        );

        return $this->loadInvoiceOrFail($invoiceId);
    }

    /** Pflichtangabe nach § 14 UStG; Stornos erben das Leistungsdatum vom Original, das fehlen kann (Import) */
    private function assertServiceDate(Invoice $invoice): void
    {
        if ($invoice->getKind()->isCancellation() || $invoice->hasServiceDateOrPeriod()) {
            return;
        }

        throw new ApiValidationException('serviceDate or servicePeriodStart/servicePeriodEnd is required', 400);
    }

    private function assertDraft(?InvoiceStatus $status): void
    {
        if ($status === null) {
            throw new BusinessInvoiceNotFoundException();
        }
        if ($status !== InvoiceStatus::Draft) {
            throw BusinessDocumentStateException::notDraft();
        }
    }

    private function loadInvoiceOrFail(InvoiceId $invoiceId): Invoice
    {
        $invoice = $this->repository->loadInvoice($invoiceId);
        if ($invoice === null) {
            throw new BusinessInvoiceNotFoundException();
        }

        return $invoice;
    }
}
