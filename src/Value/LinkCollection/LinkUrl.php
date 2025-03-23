<?php

namespace LukaLtaApi\Value\LinkCollection;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiInvalidArgumentException;

class LinkUrl
{
    private function __construct(
        private readonly string $value
    ) {
        if (empty($value)) {
            throw new ApiInvalidArgumentException(
                'URL cannot be empty',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new ApiInvalidArgumentException(
                'URL is not valid',
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
