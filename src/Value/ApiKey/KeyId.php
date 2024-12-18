<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\ApiKey;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Value\IdentifierInterface;

class KeyId implements IdentifierInterface
{

    private function __construct(
        private readonly int $linkId,
    ) {
        if ($linkId < 1) {
            throw new ApiValidationException(
                'KeyId must be greater than 0',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }
    }

    public static function fromInt(int $linkId): self
    {
        return new self($linkId);
    }

    public static function fromString(string $linkId): self
    {
        return new self((int)$linkId);
    }

    public function asString(): string
    {
        return (string)$this->linkId;
    }

    public function asInt(): int
    {
        return $this->linkId;
    }
}
