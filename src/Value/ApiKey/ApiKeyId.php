<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\ApiKey;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Value\IdentifierInterface;

class ApiKeyId implements IdentifierInterface
{
    private function __construct(
        private readonly int $keyId,
    ) {
        if ($keyId < 1) {
            throw new ApiValidationException(
                'API key ID must be greater than 0',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }
    }

    public static function fromInt(int $keyId): self
    {
        return new self($keyId);
    }

    public static function fromString(string $keyId): self
    {
        return new self((int) $keyId);
    }

    public function asString(): string
    {
        return (string) $this->keyId;
    }

    public function asInt(): int
    {
        return $this->keyId;
    }
}
