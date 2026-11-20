<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use JsonException;
use LukaLtaApi\Exception\ApiValidationException;

/** Prozentsatz echt zwischen 0 und 100 mit höchstens drei Nachkommastellen, intern in Tausendstel-Prozent */
class Percentage
{
    /** Tausendstel-Prozent je Prozent */
    private const int THOUSANDTHS_PER_PERCENT = 1000;

    /** 100 % in Tausendstel-Prozent */
    private const int HUNDRED_PERCENT = 100 * self::THOUSANDTHS_PER_PERCENT;

    /** Maximal erlaubte Nachkommastellen */
    private const int MAX_DECIMALS = 3;

    private function __construct(
        private readonly int $thousandths,
    ) {
        if ($thousandths <= 0 || $thousandths >= self::HUNDRED_PERCENT) {
            throw new ApiValidationException('percent must be greater than 0 and less than 100', 400);
        }
    }

    /** Aus einer JSON-Zahl (int oder float); die Dezimalstellen werden aus der kürzesten Darstellung gelesen */
    public static function fromJsonNumber(mixed $value): self
    {
        if (!is_int($value) && !is_float($value)) {
            throw new ApiValidationException('percent must be a number', 400);
        }
        if ($value <= 0 || $value >= 100) {
            throw new ApiValidationException('percent must be greater than 0 and less than 100', 400);
        }

        try {
            // json_encode liefert die kürzeste exakte Darstellung (33.333 statt 33.33299999…)
            $text = json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException) {
            throw new ApiValidationException('percent must be a number', 400);
        }

        $matches = [];
        $pattern = '/^(\d{1,2})(?:\.(\d{1,' . self::MAX_DECIMALS . '}))?$/';
        if (preg_match($pattern, $text, $matches) !== 1) {
            throw new ApiValidationException(
                sprintf('percent must have at most %d decimals', self::MAX_DECIMALS),
                400,
            );
        }

        $fraction = str_pad($matches[2] ?? '', self::MAX_DECIMALS, '0');

        return new self(((int) $matches[1] * self::THOUSANDTHS_PER_PERCENT) + (int) $fraction);
    }

    /** Anteil am Betrag, kaufmännisch gerundet, ausschließlich mit Integer-Arithmetik */
    public function applyTo(Money $amount): Money
    {
        $product = abs($amount->asCents()) * $this->thousandths;
        $rounded = intdiv($product + intdiv(self::HUNDRED_PERCENT, 2), self::HUNDRED_PERCENT);

        return Money::fromCents($amount->isNegative() ? -$rounded : $rounded);
    }
}
