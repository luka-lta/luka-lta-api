<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use JsonSerializable;
use LukaLtaApi\Exception\ApiValidationException;
use Stringable;

class DocumentNumber implements Stringable, JsonSerializable
{
    /** Mindestanzahl Stellen des Zählers (führende Nullen) */
    private const int SEQUENCE_PADDING = 4;

    private function __construct(
        private readonly DocumentPrefix $prefix,
        private readonly int $year,
        private readonly int $sequence,
    ) {
        if ($sequence < 1) {
            throw new ApiValidationException('Document sequence must be positive', 400);
        }
        if ($year < 1000 || $year > 9999) {
            throw new ApiValidationException('Document year must have 4 digits', 400);
        }
    }

    public static function from(DocumentPrefix $prefix, int $year, int $sequence): self
    {
        return new self($prefix, $year, $sequence);
    }

    public static function parse(string $value, DocumentPrefix $prefix): self
    {
        $matches = [];
        if (preg_match('/^(RE|AN)-(\d{4})-(\d{4,})$/', $value, $matches) !== 1) {
            throw new ApiValidationException('Invalid document number format', 400);
        }
        if ($matches[1] !== $prefix->value) {
            throw new ApiValidationException('Document number has the wrong prefix', 400);
        }

        return new self($prefix, (int)$matches[2], (int)$matches[3]);
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function asString(): string
    {
        $sequence = str_pad((string)$this->sequence, self::SEQUENCE_PADDING, '0', STR_PAD_LEFT);

        return $this->prefix->value . '-' . $this->year . '-' . $sequence;
    }

    public function __toString(): string
    {
        return $this->asString();
    }

    public function jsonSerialize(): string
    {
        return $this->asString();
    }
}
