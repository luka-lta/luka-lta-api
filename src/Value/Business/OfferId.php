<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use JsonSerializable;
use LukaLtaApi\Exception\ApiValidationException;
use Ramsey\Uuid\Uuid;
use Stringable;

class OfferId implements JsonSerializable, Stringable
{
    private function __construct(
        private readonly string $value,
    ) {
        if (!Uuid::isValid($value)) {
            throw new ApiValidationException('Offer ID must be a valid UUID', 400);
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function asString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
