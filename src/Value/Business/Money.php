<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use JsonSerializable;
use LukaLtaApi\Exception\ApiValidationException;

class Money implements JsonSerializable
{
    /** Cent pro Euro */
    private const int CENTS_PER_EURO = 100;

    /** Mengen sind in Hundertsteln gespeichert */
    private const int HUNDREDTHS_PER_UNIT = 100;

    /** Rundungsoffset für kaufmännisches Runden (halber Teiler) */
    private const int ROUNDING_OFFSET = 50;

    /** Maximale Anzahl Stellen vor dem Komma, verhindert int-Überlauf */
    private const int MAX_EURO_DIGITS = 12;

    private function __construct(
        private readonly int $cents,
    ) {
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function fromEuroString(string $value): self
    {
        $matches = [];
        $pattern = '/^(-?)(\d{1,' . self::MAX_EURO_DIGITS . '})(?:[.,](\d{1,2}))?$/';
        $isValid = preg_match($pattern, trim($value), $matches);
        if ($isValid !== 1) {
            throw new ApiValidationException('Amount must be a decimal number with at most 2 decimals', 400);
        }

        $fraction = str_pad($matches[3] ?? '', 2, '0');
        $cents = ((int)$matches[2] * self::CENTS_PER_EURO) + (int)$fraction;

        return new self($matches[1] === '-' ? -$cents : $cents);
    }

    public function asCents(): int
    {
        return $this->cents;
    }

    public function add(Money $other): Money
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(Money $other): Money
    {
        return new self($this->cents - $other->cents);
    }

    public function negate(): Money
    {
        return new self(-$this->cents);
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    /** Kaufmännisch gerundet (half away from zero), ausschließlich mit Integer-Arithmetik */
    public function multiplyByQuantity(Quantity $quantity): Money
    {
        $product = abs($this->cents) * $quantity->asHundredths();
        $rounded = intdiv($product + self::ROUNDING_OFFSET, self::HUNDREDTHS_PER_UNIT);

        return new self($this->isNegative() ? -$rounded : $rounded);
    }

    /** Deutsches Format, z.B. "1.234,50 €" */
    public function format(): string
    {
        $absolute = abs($this->cents);
        $euros = number_format(intdiv($absolute, self::CENTS_PER_EURO), 0, ',', '.');
        $cents = str_pad((string)($absolute % self::CENTS_PER_EURO), 2, '0', STR_PAD_LEFT);

        return ($this->isNegative() ? '-' : '') . $euros . ',' . $cents . ' €';
    }

    public function jsonSerialize(): int
    {
        return $this->cents;
    }
}
