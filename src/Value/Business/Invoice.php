<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use DateTimeImmutable;
use JsonSerializable;
use LukaLtaApi\Exception\ApiValidationException;

class Invoice implements JsonSerializable
{
    /** Obergrenze für das Zahlungsziel in Tagen (wie in den Firmendaten) */
    private const int MAX_PAYMENT_TERM_DAYS = 365;

    /** Zahlungsziel, falls noch keine Firmendaten gepflegt sind */
    public const int FALLBACK_PAYMENT_TERM_DAYS = 14;

    private const string DATE_FORMAT = DocumentFieldParser::DATE_FORMAT;

    /** @param ?array<string, mixed> $issuerSnapshot */
    private function __construct(
        private readonly InvoiceId $invoiceId,
        private readonly ?DocumentNumber $invoiceNumber,
        private readonly InvoiceKind $kind,
        private readonly InvoiceStatus $status,
        private readonly CustomerId $customerId,
        private readonly string $customerName,
        private readonly ?OfferId $offerId,
        private readonly ?string $offerNumber,
        private readonly ?InvoiceId $depositInvoiceId,
        private readonly ?string $depositInvoiceNumber,
        private readonly ?DateTimeImmutable $depositInvoiceDate,
        private readonly ?InvoiceId $cancelsInvoiceId,
        private readonly ?string $cancelsInvoiceNumber,
        private readonly DateTimeImmutable $invoiceDate,
        private readonly ?DateTimeImmutable $serviceDate,
        private readonly ?DateTimeImmutable $servicePeriodStart,
        private readonly ?DateTimeImmutable $servicePeriodEnd,
        private readonly int $paymentTermDays,
        private readonly ?DateTimeImmutable $storedDueDate,
        private readonly ?string $introText,
        private readonly ?string $closingText,
        private readonly LineItems $items,
        private readonly Money $subtotal,
        private readonly Money $depositDeduction,
        private readonly Money $total,
        private readonly ?string $paidAt,
        private readonly bool $isImported,
        private readonly ?PostalAddressSnapshot $customerSnapshot,
        private readonly ?array $issuerSnapshot,
        private readonly ?string $pdfObjectKey,
        private readonly ?string $createdAt,
    ) {
        if ($paymentTermDays < 0 || $paymentTermDays > self::MAX_PAYMENT_TERM_DAYS) {
            throw new ApiValidationException(
                sprintf('paymentTermDays must be between 0 and %d', self::MAX_PAYMENT_TERM_DAYS),
                400,
            );
        }
        if (($servicePeriodStart === null) !== ($servicePeriodEnd === null)) {
            throw new ApiValidationException('servicePeriodStart and servicePeriodEnd must be set together', 400);
        }
        if ($serviceDate !== null && $servicePeriodStart !== null) {
            throw new ApiValidationException('Either serviceDate or a service period may be set, not both', 400);
        }
        // Kalendertage vergleichen, nicht Zeitpunkte: die Daten können aus unterschiedlichen Zeitzonen stammen
        $isPeriodReversed = $servicePeriodStart !== null
            && $servicePeriodEnd !== null
            && $servicePeriodEnd->format(self::DATE_FORMAT) < $servicePeriodStart->format(self::DATE_FORMAT);
        if ($isPeriodReversed) {
            throw new ApiValidationException('servicePeriodEnd must not be before servicePeriodStart', 400);
        }
    }

    /**
     * Neuer Entwurf einer normalen Rechnung. Kunde (und ggf. Angebot) muss der Aufrufer bereits geprüft haben.
     * Fehlen closingText bzw. paymentTermDays im Request, gelten die übergebenen Standardwerte.
     *
     * @param array<string, mixed> $data camelCase-Felder des Requests
     */
    public static function fromRequest(
        array $data,
        Customer $customer,
        ?Offer $offer,
        ?string $defaultClosingText,
        int $defaultPaymentTermDays,
        DateTimeImmutable $today,
    ): self {
        $closingText = array_key_exists('closingText', $data)
            ? DocumentFieldParser::optionalText($data, 'closingText')
            : $defaultClosingText;
        $paymentTermDays = array_key_exists('paymentTermDays', $data)
            ? self::parsePaymentTermDays($data['paymentTermDays'])
            : $defaultPaymentTermDays;
        $items = self::parseItems($data);
        $subtotal = $items->getTotal();

        return new self(
            InvoiceId::generate(),
            null,
            InvoiceKind::Standard,
            InvoiceStatus::Draft,
            $customer->getCustomerId(),
            $customer->getDisplayName(),
            $offer?->getOfferId(),
            $offer?->getOfferNumber()?->asString(),
            null,
            null,
            null,
            null,
            null,
            DocumentFieldParser::optionalDate($data, 'invoiceDate') ?? $today,
            DocumentFieldParser::optionalDate($data, 'serviceDate'),
            DocumentFieldParser::optionalDate($data, 'servicePeriodStart'),
            DocumentFieldParser::optionalDate($data, 'servicePeriodEnd'),
            $paymentTermDays,
            null,
            DocumentFieldParser::optionalText($data, 'introText'),
            $closingText,
            $items,
            $subtotal,
            Money::fromCents(0),
            $subtotal,
            null,
            false,
            null,
            null,
            null,
            null,
        );
    }

    /** @param array<string, mixed> $row inkl. Join-Spalten aus BusinessInvoiceRepository */
    public static function fromDatabase(array $row, LineItems $items): self
    {
        $customerSnapshot = DocumentFieldParser::decodeJson($row['customer_snapshot']);
        $snapshot = $customerSnapshot !== null ? PostalAddressSnapshot::fromArray($customerSnapshot) : null;
        $invoiceNumber = $row['invoice_number'] !== null
            ? DocumentNumber::parse((string) $row['invoice_number'], DocumentPrefix::Invoice)
            : null;

        return new self(
            InvoiceId::fromString((string) $row['invoice_id']),
            $invoiceNumber,
            InvoiceKind::from((string) $row['kind']),
            InvoiceStatus::from((string) $row['status']),
            CustomerId::fromString((string) $row['customer_id']),
            $snapshot?->getDisplayName() ?? (string) $row['customer_display_name'],
            $row['offer_id'] !== null ? OfferId::fromString((string) $row['offer_id']) : null,
            $row['offer_number'],
            $row['deposit_invoice_id'] !== null ? InvoiceId::fromString((string) $row['deposit_invoice_id']) : null,
            $row['deposit_invoice_number'],
            self::dateFromDatabase($row['deposit_invoice_date']),
            $row['cancels_invoice_id'] !== null ? InvoiceId::fromString((string) $row['cancels_invoice_id']) : null,
            $row['cancels_invoice_number'],
            new DateTimeImmutable((string) $row['invoice_date']),
            self::dateFromDatabase($row['service_date']),
            self::dateFromDatabase($row['service_period_start']),
            self::dateFromDatabase($row['service_period_end']),
            (int) $row['payment_term_days'],
            self::dateFromDatabase($row['due_date']),
            $row['intro_text'],
            $row['closing_text'],
            $items,
            Money::fromCents((int) $row['subtotal_cents']),
            Money::fromCents((int) $row['deposit_deduction_cents']),
            Money::fromCents((int) $row['total_cents']),
            $row['paid_at'],
            (bool) $row['is_imported'],
            $snapshot,
            DocumentFieldParser::decodeJson($row['issuer_snapshot']),
            $row['pdf_object_key'],
            (string) $row['created_at'],
        );
    }

    /**
     * Wendet die Felder eines PATCH-Requests auf einen Entwurf an. Art, Verknüpfungen und Anzahlungsabzug
     * bleiben unverändert. Wird ein Leistungsdatum gesetzt, entfällt ein bisheriger Zeitraum und umgekehrt.
     *
     * @param array<string, mixed> $data nur die zu ändernden Felder
     */
    public function withRequestChanges(array $data, Customer $customer): self
    {
        $isServiceDateSent = ($data['serviceDate'] ?? null) !== null;
        $isPeriodSent = ($data['servicePeriodStart'] ?? null) !== null || ($data['servicePeriodEnd'] ?? null) !== null;

        $serviceDate = $this->serviceDate;
        if (array_key_exists('serviceDate', $data) || $isPeriodSent) {
            $serviceDate = DocumentFieldParser::optionalDate($data, 'serviceDate');
        }
        $periodStart = $this->servicePeriodStart;
        $periodEnd = $this->servicePeriodEnd;
        if ($isServiceDateSent && !$isPeriodSent) {
            $periodStart = null;
            $periodEnd = null;
        }
        if (array_key_exists('servicePeriodStart', $data)) {
            $periodStart = DocumentFieldParser::optionalDate($data, 'servicePeriodStart');
        }
        if (array_key_exists('servicePeriodEnd', $data)) {
            $periodEnd = DocumentFieldParser::optionalDate($data, 'servicePeriodEnd');
        }

        $items = array_key_exists('items', $data) ? self::parseItems($data) : $this->items;
        $subtotal = $items->getTotal();

        return new self(
            $this->invoiceId,
            null,
            $this->kind,
            InvoiceStatus::Draft,
            $customer->getCustomerId(),
            $customer->getDisplayName(),
            $this->offerId,
            $this->offerNumber,
            $this->depositInvoiceId,
            $this->depositInvoiceNumber,
            $this->depositInvoiceDate,
            $this->cancelsInvoiceId,
            $this->cancelsInvoiceNumber,
            array_key_exists('invoiceDate', $data)
                ? DocumentFieldParser::optionalDate($data, 'invoiceDate') ?? $this->invoiceDate
                : $this->invoiceDate,
            $serviceDate,
            $periodStart,
            $periodEnd,
            array_key_exists('paymentTermDays', $data)
                ? self::parsePaymentTermDays($data['paymentTermDays'])
                : $this->paymentTermDays,
            null,
            array_key_exists('introText', $data)
                ? DocumentFieldParser::optionalText($data, 'introText')
                : $this->introText,
            array_key_exists('closingText', $data)
                ? DocumentFieldParser::optionalText($data, 'closingText')
                : $this->closingText,
            $items,
            $subtotal,
            $this->depositDeduction,
            $subtotal->subtract($this->depositDeduction),
            null,
            false,
            null,
            null,
            null,
            $this->createdAt,
        );
    }

    public function getInvoiceId(): InvoiceId
    {
        return $this->invoiceId;
    }

    public function getInvoiceNumber(): ?DocumentNumber
    {
        return $this->invoiceNumber;
    }

    public function getKind(): InvoiceKind
    {
        return $this->kind;
    }

    public function getStatus(): InvoiceStatus
    {
        return $this->status;
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function getCustomerId(): CustomerId
    {
        return $this->customerId;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function getOfferId(): ?OfferId
    {
        return $this->offerId;
    }

    public function getOfferNumber(): ?string
    {
        return $this->offerNumber;
    }

    public function getDepositInvoiceId(): ?InvoiceId
    {
        return $this->depositInvoiceId;
    }

    public function getDepositInvoiceNumber(): ?string
    {
        return $this->depositInvoiceNumber;
    }

    public function getDepositInvoiceDate(): ?DateTimeImmutable
    {
        return $this->depositInvoiceDate;
    }

    public function getCancelsInvoiceId(): ?InvoiceId
    {
        return $this->cancelsInvoiceId;
    }

    public function getCancelsInvoiceNumber(): ?string
    {
        return $this->cancelsInvoiceNumber;
    }

    public function getInvoiceDate(): DateTimeImmutable
    {
        return $this->invoiceDate;
    }

    public function getServiceDate(): ?DateTimeImmutable
    {
        return $this->serviceDate;
    }

    public function getServicePeriodStart(): ?DateTimeImmutable
    {
        return $this->servicePeriodStart;
    }

    public function getServicePeriodEnd(): ?DateTimeImmutable
    {
        return $this->servicePeriodEnd;
    }

    public function hasServiceDateOrPeriod(): bool
    {
        return $this->serviceDate !== null || $this->servicePeriodStart !== null;
    }

    public function getPaymentTermDays(): int
    {
        return $this->paymentTermDays;
    }

    /** Festgeschrieben: gespeicherte Fälligkeit; Entwurf: Rechnungsdatum + Zahlungsziel */
    public function getDueDate(): DateTimeImmutable
    {
        return $this->storedDueDate ?? $this->invoiceDate->modify('+' . $this->paymentTermDays . ' days');
    }

    /** Überfällig = offen und Fälligkeit vor heute; Kalendertage als Y-m-d vergleichen */
    public function isOverdue(DateTimeImmutable $today): bool
    {
        return $this->status === InvoiceStatus::Open
            && $this->storedDueDate !== null
            && $this->storedDueDate->format(self::DATE_FORMAT) < $today->format(self::DATE_FORMAT);
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

    public function getSubtotal(): Money
    {
        return $this->subtotal;
    }

    /** Abzuziehende Anzahlung (Schlussrechnung positiv, deren Storno negativ, sonst 0) */
    public function getDepositDeduction(): Money
    {
        return $this->depositDeduction;
    }

    public function getTotal(): Money
    {
        return $this->total;
    }

    public function getPaidAt(): ?string
    {
        return $this->paidAt;
    }

    public function isImported(): bool
    {
        return $this->isImported;
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
     * Datenbankzeile ohne Positionen; Nummer, Fälligkeit, Snapshots und PDF setzt erst markFinalized.
     *
     * @return array<string, mixed>
     */
    public function toDatabaseRow(): array
    {
        return [
            'invoice_id' => $this->invoiceId->asString(),
            'kind' => $this->kind->value,
            'customer_id' => $this->customerId->asString(),
            'offer_id' => $this->offerId?->asString(),
            'deposit_invoice_id' => $this->depositInvoiceId?->asString(),
            'cancels_invoice_id' => $this->cancelsInvoiceId?->asString(),
            'invoice_date' => $this->invoiceDate->format(self::DATE_FORMAT),
            'service_date' => $this->serviceDate?->format(self::DATE_FORMAT),
            'service_period_start' => $this->servicePeriodStart?->format(self::DATE_FORMAT),
            'service_period_end' => $this->servicePeriodEnd?->format(self::DATE_FORMAT),
            'payment_term_days' => $this->paymentTermDays,
            'intro_text' => $this->introText,
            'closing_text' => $this->closingText,
            'subtotal_cents' => $this->subtotal->asCents(),
            'deposit_deduction_cents' => $this->depositDeduction->asCents(),
            'total_cents' => $this->total->asCents(),
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'invoiceId' => $this->invoiceId->asString(),
            'invoiceNumber' => $this->invoiceNumber?->asString(),
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'customerId' => $this->customerId->asString(),
            'customerName' => $this->customerName,
            'offerId' => $this->offerId?->asString(),
            'offerNumber' => $this->offerNumber,
            'depositInvoiceId' => $this->depositInvoiceId?->asString(),
            'depositInvoiceNumber' => $this->depositInvoiceNumber,
            'cancelsInvoiceId' => $this->cancelsInvoiceId?->asString(),
            'cancelsInvoiceNumber' => $this->cancelsInvoiceNumber,
            'invoiceDate' => $this->invoiceDate->format(self::DATE_FORMAT),
            'serviceDate' => $this->serviceDate?->format(self::DATE_FORMAT),
            'servicePeriodStart' => $this->servicePeriodStart?->format(self::DATE_FORMAT),
            'servicePeriodEnd' => $this->servicePeriodEnd?->format(self::DATE_FORMAT),
            'paymentTermDays' => $this->paymentTermDays,
            // Stornos haben keine Fälligkeit
            'dueDate' => $this->kind->isCancellation() ? null : $this->getDueDate()->format(self::DATE_FORMAT),
            'introText' => $this->introText,
            'closingText' => $this->closingText,
            'items' => $this->items->toArray(),
            'subtotalCents' => $this->subtotal->asCents(),
            'depositDeductionCents' => $this->depositDeduction->asCents(),
            'totalCents' => $this->total->asCents(),
            'paidAt' => $this->paidAt,
            'isImported' => $this->isImported,
            'isOverdue' => $this->isOverdue(BusinessClock::today()),
            // Sperre der Schlussrechnung (unbezahlte Anzahlung) folgt mit den Rechnungen aus Angeboten
            'isLocked' => false,
            'lockReason' => null,
            'hasPdf' => $this->hasPdf(),
            'createdAt' => $this->createdAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @param array<string, mixed> $data */
    private static function parseItems(array $data): LineItems
    {
        $items = $data['items'] ?? null;
        if (!is_array($items)) {
            throw new ApiValidationException('items must be an array', 400);
        }

        return LineItems::fromRequestArray($items);
    }

    private static function parsePaymentTermDays(mixed $value): int
    {
        if (!is_int($value)) {
            throw new ApiValidationException('paymentTermDays must be an integer', 400);
        }

        return $value;
    }

    private static function dateFromDatabase(mixed $value): ?DateTimeImmutable
    {
        return $value !== null ? new DateTimeImmutable((string) $value) : null;
    }
}
