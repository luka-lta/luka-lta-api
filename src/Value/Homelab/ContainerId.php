<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Value\IdentifierInterface;
use RuntimeException;

class ContainerId implements IdentifierInterface
{
    private function __construct(
        private readonly string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (empty($value)) {
            throw new ApiValidationException('Container ID cannot be empty.', 400);
        }

        return new self($value);
    }

    public function asString(): string
    {
        return $this->value;
    }

    public function asInt(): int
    {
        throw new RuntimeException('ContainerId is slug-based and cannot be cast to int.');
    }
}
