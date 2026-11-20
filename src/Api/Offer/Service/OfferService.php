<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Offer\Service;

use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Api\Business\Pdf\BusinessPdfRenderer;
use LukaLtaApi\Api\Business\Service\BusinessCustomerLookup;
use LukaLtaApi\Api\Business\Service\BusinessDocumentStorage;
use LukaLtaApi\Api\BusinessSettings\Service\BusinessSettingsService;
use LukaLtaApi\Exception\BusinessDocumentStateException;
use LukaLtaApi\Exception\BusinessOfferNotFoundException;
use LukaLtaApi\Repository\BusinessNumberSequenceRepository;
use LukaLtaApi\Repository\BusinessOfferRepository;
use LukaLtaApi\Repository\BusinessSettingsRepository;
use LukaLtaApi\Value\Business\BusinessClock;
use LukaLtaApi\Value\Business\CustomerId;
use LukaLtaApi\Value\Business\DocumentNumber;
use LukaLtaApi\Value\Business\DocumentPrefix;
use LukaLtaApi\Value\Business\Offer;
use LukaLtaApi\Value\Business\OfferId;
use LukaLtaApi\Value\Business\OfferStatus;
use LukaLtaApi\Value\Business\PdfDocument;
use LukaLtaApi\Value\Business\PdfFile;
use LukaLtaApi\Value\Business\PostalAddressSnapshot;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Throwable;

class OfferService
{
    private const string PDF_TITLE = 'Angebot';
    private const string PDF_KEY_PREFIX = 'business/offers/';
    private const string DRAFT_FILE_NAME = 'Angebot-Entwurf.pdf';
    private const string DISPLAY_DATE_FORMAT = 'd.m.Y';

    public function __construct(
        private readonly BusinessOfferRepository $repository,
        private readonly BusinessNumberSequenceRepository $sequenceRepository,
        private readonly BusinessCustomerLookup $customerLookup,
        private readonly BusinessSettingsRepository $settingsRepository,
        private readonly BusinessSettingsService $settingsService,
        private readonly BusinessPdfRenderer $pdfRenderer,
        private readonly BusinessDocumentStorage $documentStorage,
    ) {
    }

    public function listOffers(?OfferStatus $status, ?CustomerId $customerId, ?int $year): ApiResult
    {
        $offers = $this->repository->loadOffers($status, $customerId, $year);

        return ApiResult::from(JsonResult::from('Offers fetched', ['offers' => $offers->toArray()]));
    }

    public function getOffer(OfferId $offerId): ApiResult
    {
        return $this->offerResult('Offer fetched', $this->loadOfferOrFail($offerId));
    }

    /** @param array<string, mixed> $data */
    public function createOffer(array $data): ApiResult
    {
        $customer = $this->customerLookup->loadActiveCustomerOrFail($this->customerLookup->parseCustomerId($data));
        // Firmendaten dürfen beim Entwurf noch fehlen, dann gibt es eben keinen Standard-Schlusstext
        $defaultClosingText = $this->settingsRepository->loadSettings()?->getFooterText();
        $offer = Offer::fromRequest($data, $customer, $defaultClosingText, $this->today());

        $this->repository->executeInTransaction(fn() => $this->repository->create($offer));

        return $this->offerResult(
            'Offer created',
            $this->loadOfferOrFail($offer->getOfferId()),
            StatusCodeInterface::STATUS_CREATED,
        );
    }

    /** @param array<string, mixed> $data nur die zu ändernden Felder */
    public function updateOffer(OfferId $offerId, array $data): ApiResult
    {
        $existing = $this->loadOfferOrFail($offerId);
        $this->assertDraft($existing->getStatus());

        $merged = array_replace($existing->toArray(), $data);
        // Neues Angebotsdatum ohne explizites Ablaufdatum: Standard-Gültigkeit neu berechnen
        if (array_key_exists('offerDate', $data) && !array_key_exists('validUntil', $data)) {
            unset($merged['validUntil']);
        }

        $customerId = $this->customerLookup->parseCustomerId($merged);
        $customer = $customerId->asString() === $existing->getCustomerId()->asString()
            ? $this->customerLookup->loadCustomerOrFail($customerId)
            : $this->customerLookup->loadActiveCustomerOrFail($customerId);
        $offer = Offer::fromRequest($merged, $customer, null, $this->today(), $offerId);

        $this->repository->executeInTransaction(function () use ($offer): void {
            $this->assertDraft($this->repository->lockOfferStatus($offer->getOfferId()));
            $this->repository->update($offer);
        });

        return $this->getOffer($offerId);
    }

    public function deleteOffer(OfferId $offerId): ApiResult
    {
        $this->loadOfferOrFail($offerId);

        $this->repository->executeInTransaction(function () use ($offerId): void {
            $this->assertDraft($this->repository->lockOfferStatus($offerId));
            $this->repository->delete($offerId);
        });

        return ApiResult::from(JsonResult::from('Offer deleted'));
    }

    /**
     * Schreibt das Angebot fest. Nummernvergabe, PDF-Ablage und Statuswechsel laufen in einer Transaktion
     * auf derselben PDO-Verbindung: schlägt etwas fehl, wird die Nummer nicht verbraucht und das bereits
     * hochgeladene PDF wieder gelöscht.
     */
    public function send(OfferId $offerId): ApiResult
    {
        $settings = $this->settingsService->loadCompleteOrFail();
        $this->assertDraft($this->loadOfferOrFail($offerId)->getStatus());
        $logoBytes = $this->documentStorage->loadLogoBytes($settings);
        $pdfObjectKey = $this->documentStorage->createPdfObjectKey(self::PDF_KEY_PREFIX, $offerId->asString());
        $isUploaded = false;

        try {
            $this->repository->executeInTransaction(
                function () use ($offerId, $settings, $logoBytes, $pdfObjectKey, &$isUploaded): void {
                    $this->assertDraft($this->repository->lockOfferStatus($offerId));
                    // Erst nach der Sperre laden: ein paralleles PATCH kann nicht mehr dazwischen committen,
                    // PDF, Snapshot und Nummernjahr entsprechen also genau der festgeschriebenen Zeile
                    $offer = $this->loadOfferOrFail($offerId);
                    $customer = $this->customerLookup->loadCustomerOrFail($offer->getCustomerId());

                    $year = (int) $offer->getOfferDate()->format('Y');
                    $offerNumber = $this->sequenceRepository->allocateNext(DocumentPrefix::Offer, $year);
                    $customerSnapshot = PostalAddressSnapshot::fromCustomer($customer);
                    $issuerSnapshot = $settings->toSnapshot();

                    $pdf = $this->pdfRenderer->render(
                        $this->buildPdfDocument($offer, $offerNumber, $customerSnapshot, $issuerSnapshot, $logoBytes),
                    );
                    $this->documentStorage->storePdf($pdfObjectKey, $pdf);
                    $isUploaded = true;

                    $this->repository->markSent(
                        $offer->getOfferId(),
                        $offerNumber,
                        $customerSnapshot->toArray(),
                        $issuerSnapshot,
                        $pdfObjectKey,
                    );
                },
            );
        } catch (Throwable $exception) {
            // Greift auch, wenn erst der Commit scheitert
            if ($isUploaded) {
                $this->documentStorage->deleteUploadedPdf($pdfObjectKey);
            }

            throw $exception;
        }

        return $this->getOffer($offerId);
    }

    public function setStatus(OfferId $offerId, OfferStatus $status): ApiResult
    {
        $this->loadOfferOrFail($offerId);

        $this->repository->executeInTransaction(function () use ($offerId, $status): void {
            if ($this->repository->lockOfferStatus($offerId) !== OfferStatus::Sent) {
                throw BusinessDocumentStateException::notSent();
            }
            $this->repository->setStatus($offerId, $status);
        });

        return $this->getOffer($offerId);
    }

    public function duplicate(OfferId $offerId): ApiResult
    {
        $source = $this->loadOfferOrFail($offerId);
        $customer = $this->customerLookup->loadActiveCustomerOrFail($source->getCustomerId());
        $copy = $source->duplicateAsDraft($customer, $this->today());

        $this->repository->executeInTransaction(fn() => $this->repository->create($copy));

        return $this->offerResult(
            'Offer duplicated',
            $this->loadOfferOrFail($copy->getOfferId()),
            StatusCodeInterface::STATUS_CREATED,
        );
    }

    /**
     * Versendet: das gespeicherte PDF. Entwurf: Vorschau mit Wasserzeichen aus aktuellen Kunden- und
     * Firmendaten, wird nicht gespeichert.
     */
    public function loadPdf(OfferId $offerId): PdfFile
    {
        $offer = $this->loadOfferOrFail($offerId);
        if (!$offer->isDraft()) {
            return $this->loadStoredPdf($offer);
        }

        $settings = $this->settingsService->loadCompleteOrFail();
        $customer = $this->customerLookup->loadCustomerOrFail($offer->getCustomerId());
        $document = $this->buildPdfDocument(
            $offer,
            null,
            PostalAddressSnapshot::fromCustomer($customer),
            $settings->toSnapshot(),
            $this->documentStorage->loadLogoBytes($settings),
        );

        return PdfFile::from(self::DRAFT_FILE_NAME, $this->pdfRenderer->render($document));
    }

    public function loadOfferOrFail(OfferId $offerId): Offer
    {
        $offer = $this->repository->loadOffer($offerId);
        if ($offer === null) {
            throw new BusinessOfferNotFoundException();
        }

        return $offer;
    }

    private function loadStoredPdf(Offer $offer): PdfFile
    {
        $objectKey = $offer->getPdfObjectKey();
        $offerNumber = $offer->getOfferNumber();
        $body = $this->documentStorage->loadPdf($objectKey);
        if ($body === null || $offerNumber === null) {
            throw new BusinessOfferNotFoundException('Offer PDF not found');
        }

        return PdfFile::from($offerNumber->asString() . '.pdf', $body);
    }

    /** @param array<string, mixed> $issuerSnapshot */
    private function buildPdfDocument(
        Offer $offer,
        ?DocumentNumber $offerNumber,
        PostalAddressSnapshot $recipient,
        array $issuerSnapshot,
        ?string $logoBytes,
    ): PdfDocument {
        $total = $offer->getTotal();

        return PdfDocument::create(
            self::PDF_TITLE,
            $offerNumber?->asString(),
            $offer->getOfferDate(),
            [
                ['label' => 'Gültig bis', 'value' => $offer->getValidUntil()->format(self::DISPLAY_DATE_FORMAT)],
            ],
            $issuerSnapshot,
            $recipient->getAddressLines(),
            $offer->getIntroText(),
            $offer->getItems(),
            $total,
            null,
            $total,
            $offer->getClosingText(),
            $offerNumber === null,
            $logoBytes,
        );
    }

    private function assertDraft(?OfferStatus $status): void
    {
        if ($status === null) {
            throw new BusinessOfferNotFoundException();
        }
        if ($status !== OfferStatus::Draft) {
            throw BusinessDocumentStateException::notDraft();
        }
    }

    private function offerResult(
        string $message,
        Offer $offer,
        int $status = StatusCodeInterface::STATUS_OK,
    ): ApiResult {
        return ApiResult::from(JsonResult::from($message, ['offer' => $offer->toArray()]), $status);
    }

    private function today(): DateTimeImmutable
    {
        return BusinessClock::today();
    }
}
