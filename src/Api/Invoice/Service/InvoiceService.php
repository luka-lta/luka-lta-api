<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Invoice\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Api\Business\Pdf\BusinessPdfRenderer;
use LukaLtaApi\Api\Business\Service\BusinessCustomerLookup;
use LukaLtaApi\Api\Business\Service\BusinessDocumentStorage;
use LukaLtaApi\Api\BusinessSettings\Service\BusinessSettingsService;
use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Exception\BusinessDocumentStateException;
use LukaLtaApi\Exception\BusinessInvoiceNotFoundException;
use LukaLtaApi\Exception\BusinessOfferNotFoundException;
use LukaLtaApi\Repository\BusinessInvoiceRepository;
use LukaLtaApi\Repository\BusinessOfferRepository;
use LukaLtaApi\Repository\BusinessSettingsRepository;
use LukaLtaApi\Value\Business\BusinessClock;
use LukaLtaApi\Value\Business\CustomerId;
use LukaLtaApi\Value\Business\Invoice;
use LukaLtaApi\Value\Business\InvoiceId;
use LukaLtaApi\Value\Business\InvoiceKind;
use LukaLtaApi\Value\Business\InvoiceStatus;
use LukaLtaApi\Value\Business\Offer;
use LukaLtaApi\Value\Business\OfferId;
use LukaLtaApi\Value\Business\OfferStatus;
use LukaLtaApi\Value\Business\PdfFile;
use LukaLtaApi\Value\Business\PostalAddressSnapshot;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;

class InvoiceService
{
    private const string DRAFT_FILE_NAME = 'Entwurf.pdf';

    public function __construct(
        private readonly BusinessInvoiceRepository $repository,
        private readonly BusinessOfferRepository $offerRepository,
        private readonly BusinessSettingsRepository $settingsRepository,
        private readonly BusinessSettingsService $settingsService,
        private readonly BusinessCustomerLookup $customerLookup,
        private readonly BusinessDocumentStorage $documentStorage,
        private readonly BusinessPdfRenderer $pdfRenderer,
        private readonly InvoicePdfDocumentFactory $pdfDocumentFactory,
        private readonly InvoiceFinalizer $finalizer,
    ) {
    }

    public function listInvoices(
        ?InvoiceStatus $status,
        ?CustomerId $customerId,
        ?int $year,
        bool $onlyOverdue,
    ): ApiResult {
        $invoices = $this->repository->loadInvoices($status, $customerId, $year, $onlyOverdue);

        return ApiResult::from(JsonResult::from('Invoices fetched', ['invoices' => $invoices->toArray()]));
    }

    public function getInvoice(InvoiceId $invoiceId): ApiResult
    {
        return $this->invoiceResult('Invoice fetched', $this->loadInvoiceOrFail($invoiceId));
    }

    /**
     * Legt einen Entwurf einer normalen Rechnung an. Anzahlungs-, Schluss- und Stornorechnungen entstehen
     * über eigene Wege (Angebot bzw. Storno), nicht über diesen Endpunkt.
     *
     * @param array<string, mixed> $data
     */
    public function createInvoice(array $data): ApiResult
    {
        $kind = $data['kind'] ?? InvoiceKind::Standard->value;
        if ($kind !== InvoiceKind::Standard->value) {
            throw new ApiValidationException('kind must be standard', 400);
        }

        $customer = $this->customerLookup->loadActiveCustomerOrFail($this->customerLookup->parseCustomerId($data));
        $offer = $this->loadReferencedOffer($data['offerId'] ?? null);
        if ($offer !== null && $offer->getCustomerId()->asString() !== $customer->getCustomerId()->asString()) {
            throw new ApiValidationException('offerId belongs to a different customer', 400);
        }
        // Firmendaten dürfen beim Entwurf noch fehlen, dann gelten die allgemeinen Standardwerte
        $settings = $this->settingsRepository->loadSettings();
        $invoice = Invoice::fromRequest(
            $data,
            $customer,
            $offer,
            $settings?->getFooterText(),
            $settings?->getDefaultPaymentTermDays() ?? Invoice::FALLBACK_PAYMENT_TERM_DAYS,
            BusinessClock::today(),
        );

        $this->repository->executeInTransaction(fn() => $this->repository->create($invoice));

        return $this->invoiceResult(
            'Invoice created',
            $this->loadInvoiceOrFail($invoice->getInvoiceId()),
            StatusCodeInterface::STATUS_CREATED,
        );
    }

    /** @param array<string, mixed> $data nur die zu ändernden Felder */
    public function updateInvoice(InvoiceId $invoiceId, array $data): ApiResult
    {
        $existing = $this->loadInvoiceOrFail($invoiceId);
        $this->assertDraft($existing->getStatus());

        $customerId = array_key_exists('customerId', $data)
            ? $this->customerLookup->parseCustomerId($data)
            : $existing->getCustomerId();
        $isCustomerChanged = $customerId->asString() !== $existing->getCustomerId()->asString();
        if ($isCustomerChanged && $existing->getOfferId() !== null) {
            throw new ApiValidationException('customerId cannot change for an invoice linked to an offer', 400);
        }
        // Ein inzwischen archivierter Kunde darf am Entwurf bleiben, ein Wechsel nur auf aktive Kunden
        $customer = $isCustomerChanged
            ? $this->customerLookup->loadActiveCustomerOrFail($customerId)
            : $this->customerLookup->loadCustomerOrFail($customerId);

        $this->repository->executeInTransaction(function () use ($invoiceId, $data, $customer): void {
            $this->assertDraft($this->repository->lockInvoiceStatus($invoiceId));
            // Unter Sperre neu laden, damit ein paralleles PATCH nicht überschrieben wird
            $invoice = $this->loadInvoiceOrFail($invoiceId)->withRequestChanges($data, $customer);
            $this->repository->update($invoice);
        });

        return $this->getInvoice($invoiceId);
    }

    public function deleteInvoice(InvoiceId $invoiceId): ApiResult
    {
        $this->loadInvoiceOrFail($invoiceId);

        $this->repository->executeInTransaction(function () use ($invoiceId): void {
            $this->assertDraft($this->repository->lockInvoiceStatus($invoiceId));
            $this->repository->delete($invoiceId);
        });

        return ApiResult::from(JsonResult::from('Invoice deleted'));
    }

    public function finalize(InvoiceId $invoiceId): ApiResult
    {
        return $this->invoiceResult('Invoice finalized', $this->finalizer->finalize($invoiceId));
    }

    /**
     * Festgeschrieben: das gespeicherte PDF. Entwurf: Vorschau mit Wasserzeichen aus aktuellen Kunden- und
     * Firmendaten, wird nicht gespeichert.
     */
    public function loadPdf(InvoiceId $invoiceId): PdfFile
    {
        $invoice = $this->loadInvoiceOrFail($invoiceId);
        if (!$invoice->isDraft()) {
            return $this->loadStoredPdf($invoice);
        }

        $settings = $this->settingsService->loadCompleteOrFail();
        $customer = $this->customerLookup->loadCustomerOrFail($invoice->getCustomerId());
        $document = $this->pdfDocumentFactory->create(
            $invoice,
            null,
            $invoice->getDueDate(),
            PostalAddressSnapshot::fromCustomer($customer),
            $settings->toSnapshot(),
            $this->documentStorage->loadLogoBytes($settings),
        );

        return PdfFile::from(self::DRAFT_FILE_NAME, $this->pdfRenderer->render($document));
    }

    public function loadInvoiceOrFail(InvoiceId $invoiceId): Invoice
    {
        $invoice = $this->repository->loadInvoice($invoiceId);
        if ($invoice === null) {
            throw new BusinessInvoiceNotFoundException();
        }

        return $invoice;
    }

    private function loadStoredPdf(Invoice $invoice): PdfFile
    {
        $invoiceNumber = $invoice->getInvoiceNumber();
        $body = $this->documentStorage->loadPdf($invoice->getPdfObjectKey());
        if ($body === null || $invoiceNumber === null) {
            throw new BusinessInvoiceNotFoundException('Invoice PDF not found');
        }

        return PdfFile::from($invoiceNumber->asString() . '.pdf', $body);
    }

    /** Optionaler Bezug auf ein angenommenes Angebot */
    private function loadReferencedOffer(mixed $offerId): ?Offer
    {
        if ($offerId === null) {
            return null;
        }
        if (!is_string($offerId)) {
            throw new ApiValidationException('offerId must be a string', 400);
        }

        $offer = $this->offerRepository->loadOffer(OfferId::fromString($offerId));
        if ($offer === null) {
            throw new BusinessOfferNotFoundException();
        }
        if ($offer->getStatus() !== OfferStatus::Accepted) {
            throw BusinessDocumentStateException::notAccepted();
        }

        return $offer;
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

    private function invoiceResult(
        string $message,
        Invoice $invoice,
        int $status = StatusCodeInterface::STATUS_OK,
    ): ApiResult {
        return ApiResult::from(JsonResult::from($message, ['invoice' => $invoice->toArray()]), $status);
    }
}
