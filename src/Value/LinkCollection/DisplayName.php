<?php

namespace LukaLtaApi\Value\LinkCollection;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiInvalidArgumentException;

class DisplayName
{
    private function __construct(
        private readonly string $value
    ) {
        if (empty($value)) {
            throw new ApiInvalidArgumentException(
                'Display name must not be empty',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        if (strlen($value) > 255) {
            throw new ApiInvalidArgumentException(
                'Display name must not be longer than 255 characters',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
