<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use DateTimeImmutable;

/** Reines Datenobjekt für die PDF-Vorlage; enthält keine Logik */
class PdfDocument
{
    /**
     * @param string $title Angebot|Rechnung|Anzahlungsrechnung|Schlussrechnung|Stornorechnung
     * @param list<array{label: string, value: string}> $metaRows
     * @param array<string, mixed> $issuer Ergebnis von BusinessSettings::toSnapshot()
     * @param list<string> $recipientLines
     * @param ?array{label: string, amount: Money} $depositDeduction abzuziehende Anzahlung
     * @param ?string $logoBytes rohe PNG-/JPEG-Bytes
     */
    private function __construct(
        private readonly string $title,
        private readonly ?string $number,
        private readonly DateTimeImmutable $date,
        private readonly array $metaRows,
        private readonly array $issuer,
        private readonly array $recipientLines,
        private readonly ?string $introText,
        private readonly LineItems $items,
        private readonly Money $subtotal,
        private readonly ?array $depositDeduction,
        private readonly Money $total,
        private readonly ?string $closingText,
        private readonly bool $isDraft,
        private readonly ?string $logoBytes,
    ) {
    }

    /**
     * @param list<array{label: string, value: string}> $metaRows
     * @param array<string, mixed> $issuer
     * @param list<string> $recipientLines
     * @param ?array{label: string, amount: Money} $depositDeduction
     */
    public static function create(
        string $title,
        ?string $number,
        DateTimeImmutable $date,
        array $metaRows,
        array $issuer,
        array $recipientLines,
        ?string $introText,
        LineItems $items,
        Money $subtotal,
        ?array $depositDeduction,
        Money $total,
        ?string $closingText,
        bool $isDraft,
        ?string $logoBytes,
    ): self {
        return new self(
            $title,
            $number,
            $date,
            $metaRows,
            $issuer,
            $recipientLines,
            $introText,
            $items,
            $subtotal,
            $depositDeduction,
            $total,
            $closingText,
            $isDraft,
            $logoBytes,
        );
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    /** @return list<array{label: string, value: string}> */
    public function getMetaRows(): array
    {
        return $this->metaRows;
    }

    /** @return array<string, mixed> */
    public function getIssuer(): array
    {
        return $this->issuer;
    }

    /** @return list<string> */
    public function getRecipientLines(): array
    {
        return $this->recipientLines;
    }

    public function getIntroText(): ?string
    {
        return $this->introText;
    }

    public function getItems(): LineItems
    {
        return $this->items;
    }

    public function getSubtotal(): Money
    {
        return $this->subtotal;
    }

    /** @return ?array{label: string, amount: Money} */
    public function getDepositDeduction(): ?array
    {
        return $this->depositDeduction;
    }

    public function getTotal(): Money
    {
        return $this->total;
    }

    public function getClosingText(): ?string
    {
        return $this->closingText;
    }

    public function isDraft(): bool
    {
        return $this->isDraft;
    }

    public function getLogoBytes(): ?string
    {
        return $this->logoBytes;
    }
}
