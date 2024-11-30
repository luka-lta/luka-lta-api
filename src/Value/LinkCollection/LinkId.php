<?php

namespace LukaLtaApi\Value\LinkCollection;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Value\IdentifierInterface;

class LinkId implements IdentifierInterface
{
    private function __construct(
        private readonly int $linkId,
    ) {
        if ($linkId < 1) {
            throw new ApiValidationException(
                'Link ID must be greater than 0',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }
    }

    public static function fromInt(int $linkId): self
    {
        return new self($linkId);
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
