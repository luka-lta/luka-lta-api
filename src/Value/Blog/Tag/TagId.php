<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Blog\Tag;

use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Value\IdentifierInterface;

class TagId implements IdentifierInterface
{
    private function __construct(
        private readonly int $value,
    ) {
        if ($value <= 0) {
            throw new ApiValidationException('Tag ID must be greater than zero.', 400);
        }
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public static function fromString(string $value): self
    {
        return new self((int) $value);
    }

    public function asInt(): int
    {
        return $this->value;
    }

    public function asString(): string
    {
        return (string) $this->value;
    }
}
