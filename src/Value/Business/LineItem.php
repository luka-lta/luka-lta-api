<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use JsonSerializable;
use LukaLtaApi\Exception\ApiValidationException;

class LineItem implements JsonSerializable
{
    /** Maximale Länge der Positionsbeschreibung in Zeichen */
    private const int MAX_DESCRIPTION_LENGTH = 2000;

    /** Maximale Länge der Einheit in Zeichen */
    private const int MAX_UNIT_LENGTH = 30;

    private function __construct(
        private readonly int $position,
        private readonly string $description,
        private readonly Quantity $quantity,
        private readonly string $unit,
        private readonly Money $unitPrice,
    ) {
        if ($position < 1) {
            throw new ApiValidationException('Line item position must be positive', 400);
        }
        if ($description === '') {
            throw new ApiValidationException('Line item description must not be empty', 400);
        }
        if (mb_strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            throw new ApiValidationException('Line item description is too long', 400);
        }
        if (mb_strlen($unit) > self::MAX_UNIT_LENGTH) {
            throw new ApiValidationException('Line item unit is too long', 400);
        }
    }

    public static function create(
        int $position,
        string $description,
        Quantity $quantity,
        string $unit,
        Money $unitPrice,
    ): self {
        return new self($position, trim($description), $quantity, trim($unit), $unitPrice);
    }

    /** @param array<string, mixed> $row */
    public static function fromDatabase(array $row): self
    {
        return self::create(
            (int)$row['position'],
            (string)$row['description'],
            Quantity::fromHundredths((int)$row['quantity_hundredths']),
            (string)$row['unit'],
            Money::fromCents((int)$row['unit_price_cents']),
        );
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getQuantity(): Quantity
    {
        return $this->quantity;
    }

    public function getUnitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function getLineTotal(): Money
    {
        return $this->unitPrice->multiplyByQuantity($this->quantity);
    }

    /** Für Stornos: Einzelpreis (und damit Summe) negiert, Menge bleibt positiv */
    public function negated(): LineItem
    {
        return new self($this->position, $this->description, $this->quantity, $this->unit, $this->unitPrice->negate());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'position' => $this->position,
            'description' => $this->description,
            'quantity' => $this->quantity->format(),
            'unit' => $this->unit,
            'unitPriceCents' => $this->unitPrice->asCents(),
            'lineTotalCents' => $this->getLineTotal()->asCents(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
