<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use DateTimeImmutable;
use JsonSerializable;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Exception\ApiValidationException;

class Offer implements JsonSerializable
{
    /** Standard-Gültigkeit eines Angebots in Tagen ab Angebotsdatum */
    public const int DEFAULT_VALIDITY_DAYS = 30;

    private const string DATE_FORMAT = 'Y-m-d';

    /** @param ?array<string, mixed> $issuerSnapshot */
    private function __construct(
        private readonly OfferId $offerId,
        private readonly ?DocumentNumber $offerNumber,
        private readonly OfferStatus $status,
        private readonly CustomerId $customerId,
        private readonly string $customerName,
        private readonly DateTimeImmutable $offerDate,
        private readonly DateTimeImmutable $validUntil,
        private readonly ?string $introText,
        private readonly ?string $closingText,
        private readonly LineItems $items,
        private readonly ?PostalAddressSnapshot $customerSnapshot,
        private readonly ?array $issuerSnapshot,
        private readonly ?string $pdfObjectKey,
        private readonly ?string $depositInvoiceId,
        private readonly ?string $finalInvoiceId,
        private readonly ?string $createdAt,
    ) {
        // Kalendertage vergleichen, nicht Zeitpunkte: die Daten können aus unterschiedlichen Zeitzonen stammen
        if ($validUntil->format(self::DATE_FORMAT) < $offerDate->format(self::DATE_FORMAT)) {
            throw new ApiValidationException('validUntil must not be before offerDate', 400);
        }
    }

    /**
     * Erzeugt einen Entwurf aus Request-Daten. Der Kunde muss vom Aufrufer bereits geladen sein.
     * Fehlt closingText im Request, wird der Standard-Schlusstext aus den Firmendaten übernommen.
     *
     * @param array<string, mixed> $data camelCase-Felder des Requests
     */
    public static function fromRequest(
        array $data,
        Customer $customer,
        ?string $defaultClosingText,
        DateTimeImmutable $today,
        ?OfferId $offerId = null,
    ): self {
        $offerDate = DocumentFieldParser::optionalDate($data, 'offerDate') ?? $today;
        $validUntil = DocumentFieldParser::optionalDate($data, 'validUntil') ?? self::defaultValidUntil($offerDate);
        $closingText = array_key_exists('closingText', $data)
            ? DocumentFieldParser::optionalText($data, 'closingText')
            : $defaultClosingText;
        $items = $data['items'] ?? null;
        if (!is_array($items)) {
            throw new ApiValidationException('items must be an array', 400);
        }

        return new self(
            $offerId ?? OfferId::generate(),
            null,
            OfferStatus::Draft,
            $customer->getCustomerId(),
            $customer->getDisplayName(),
            $offerDate,
            $validUntil,
            DocumentFieldParser::optionalText($data, 'introText'),
            $closingText,
            LineItems::fromRequestArray($items),
            null,
            null,
            null,
            null,
            null,
            null,
        );
    }

    /**
     * @param array<string, mixed> $row
     * @throws ApiDatabaseException
     */
    public static function fromDatabase(array $row, LineItems $items): self
    {
        $customerSnapshot = DocumentFieldParser::decodeJson($row['customer_snapshot']);
        $snapshot = $customerSnapshot !== null ? PostalAddressSnapshot::fromArray($customerSnapshot) : null;
        $offerNumber = $row['offer_number'] !== null
            ? DocumentNumber::parse((string) $row['offer_number'], DocumentPrefix::Offer)
            : null;

        return new self(
            OfferId::fromString((string) $row['offer_id']),
            $offerNumber,
            OfferStatus::from((string) $row['status']),
            CustomerId::fromString((string) $row['customer_id']),
            $snapshot?->getDisplayName() ?? (string) $row['customer_display_name'],
            new DateTimeImmutable((string) $row['offer_date']),
            new DateTimeImmutable((string) $row['valid_until']),
            $row['intro_text'],
            $row['closing_text'],
            $items,
            $snapshot,
            DocumentFieldParser::decodeJson($row['issuer_snapshot']),
            $row['pdf_object_key'],
            $row['deposit_invoice_id'],
            $row['final_invoice_id'],
            (string) $row['created_at'],
        );
    }

    public static function defaultValidUntil(DateTimeImmutable $offerDate): DateTimeImmutable
    {
        return $offerDate->modify('+' . self::DEFAULT_VALIDITY_DAYS . ' days');
    }

    /** Neuer Entwurf mit gleichem Kunden, gleichen Texten und Positionen, aber heutigem Datum */
    public function duplicateAsDraft(Customer $customer, DateTimeImmutable $today): self
    {
        return new self(
            OfferId::generate(),
            null,
            OfferStatus::Draft,
            $customer->getCustomerId(),
            $customer->getDisplayName(),
            $today,
            self::defaultValidUntil($today),
            $this->introText,
            $this->closingText,
            $this->items,
            null,
            null,
            null,
            null,
            null,
            null,
        );
    }

    public function getOfferId(): OfferId
    {
        return $this->offerId;
    }

    public function getOfferNumber(): ?DocumentNumber
    {
        return $this->offerNumber;
    }

    public function getStatus(): OfferStatus
    {
        return $this->status;
    }

    public function isDraft(): bool
    {
        return $this->status === OfferStatus::Draft;
    }

    public function getCustomerId(): CustomerId
    {
        return $this->customerId;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function getOfferDate(): DateTimeImmutable
    {
        return $this->offerDate;
    }

    public function getValidUntil(): DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function getIntroText(): ?string
    {
        return $this->introText;
    }

    public function getClosingText(): ?string
    {
        return $this->closingText;
    }

    public function getItems(): LineItems
    {
        return $this->items;
    }

    public function getTotal(): Money
    {
        return $this->items->getTotal();
    }

    public function getCustomerSnapshot(): ?PostalAddressSnapshot
    {
        return $this->customerSnapshot;
    }

    /** @return ?array<string, mixed> */
    public function getIssuerSnapshot(): ?array
    {
        return $this->issuerSnapshot;
    }

    public function getPdfObjectKey(): ?string
    {
        return $this->pdfObjectKey;
    }

    public function hasPdf(): bool
    {
        return $this->pdfObjectKey !== null;
    }

    /**
     * Datenbankzeile ohne Positionen; Nummer, Snapshots und PDF setzt erst markSent.
     *
     * @return array<string, mixed>
     */
    public function toDatabaseRow(): array
    {
        return [
            'offer_id' => $this->offerId->asString(),
            'customer_id' => $this->customerId->asString(),
            'offer_date' => $this->offerDate->format(self::DATE_FORMAT),
            'valid_until' => $this->validUntil->format(self::DATE_FORMAT),
            'intro_text' => $this->introText,
            'closing_text' => $this->closingText,
            'total_cents' => $this->getTotal()->asCents(),
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'offerId' => $this->offerId->asString(),
            'offerNumber' => $this->offerNumber?->asString(),
            'status' => $this->status->value,
            'customerId' => $this->customerId->asString(),
            'customerName' => $this->customerName,
            'offerDate' => $this->offerDate->format(self::DATE_FORMAT),
            'validUntil' => $this->validUntil->format(self::DATE_FORMAT),
            'introText' => $this->introText,
            'closingText' => $this->closingText,
            'items' => $this->items->toArray(),
            'totalCents' => $this->getTotal()->asCents(),
            'hasPdf' => $this->hasPdf(),
            'depositInvoiceId' => $this->depositInvoiceId,
            'finalInvoiceId' => $this->finalInvoiceId,
            'createdAt' => $this->createdAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
