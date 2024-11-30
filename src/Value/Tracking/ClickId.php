<?php

namespace LukaLtaApi\Value\Tracking;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Value\IdentifierInterface;

class ClickId implements IdentifierInterface
{
    private function __construct(
        private readonly int $clickId,
    ) {
        if ($clickId < 1) {
            throw new ApiValidationException(
                'Link ID must be greater than 0',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }
    }

    public static function fromInt(int $clickId): self
    {
        return new self($clickId);
    }

    public function asString(): string
    {
        return (string)$this->clickId;
    }

    public function asInt(): int
    {
        return $this->clickId;
    }
}
