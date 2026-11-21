<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use JsonSerializable;
use LukaLtaApi\Exception\ApiValidationException;

class Quantity implements JsonSerializable
{
    /** Mengen sind in Hundertsteln gespeichert */
    private const int HUNDREDTHS_PER_UNIT = 100;

    /** Maximale Anzahl Stellen vor dem Komma, verhindert int-Überlauf */
    private const int MAX_WHOLE_DIGITS = 7;

    private function __construct(
        private readonly int $hundredths,
    ) {
        if ($hundredths <= 0) {
            throw new ApiValidationException('Quantity must be greater than zero', 400);
        }
    }

    public static function fromHundredths(int $hundredths): self
    {
        return new self($hundredths);
    }

    public static function fromDecimalString(string $value): self
    {
        $matches = [];
        $isValid = preg_match('/^(\d{1,' . self::MAX_WHOLE_DIGITS . '})(?:[.,](\d{1,2}))?$/', trim($value), $matches);
        if ($isValid !== 1) {
            throw new ApiValidationException('Quantity must be a positive number with at most 2 decimals', 400);
        }

        $fraction = str_pad($matches[2] ?? '', 2, '0');

        return new self(((int)$matches[1] * self::HUNDREDTHS_PER_UNIT) + (int)$fraction);
    }

    public function asHundredths(): int
    {
        return $this->hundredths;
    }

    /** Maschinenlesbares Format mit Punkt ohne überflüssige Nullen, z.B. "1.5" oder "2" */
    public function asDecimalString(): string
    {
        return str_replace(',', '.', $this->format());
    }

    /** Deutsches Format ohne überflüssige Nullen, z.B. "1,5" oder "2" */
    public function format(): string
    {
        $whole = intdiv($this->hundredths, self::HUNDREDTHS_PER_UNIT);
        $fraction = rtrim(str_pad((string)($this->hundredths % self::HUNDREDTHS_PER_UNIT), 2, '0', STR_PAD_LEFT), '0');
        if ($fraction === '') {
            return (string)$whole;
        }

        return $whole . ',' . $fraction;
    }

    public function jsonSerialize(): string
    {
        return $this->format();
    }
}
