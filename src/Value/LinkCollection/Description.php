<?php

namespace LukaLtaApi\Value\LinkCollection;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiInvalidArgumentException;

class Description
{
    private function __construct(
        private readonly ?string $value
    ) {
        if ($value === null) {
            return;
        }

        if (empty($value)) {
            throw new ApiInvalidArgumentException(
                'Description cannot be empty',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        if (!preg_match('/^[A-Za-z0-9,.\s!?]{1,500}$/', $value)) {
            throw new ApiInvalidArgumentException(
                'Description must not be longer than 500 characters and can only contain letters, numbers, spaces, and the following characters: , . ! ?',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }
    }

    public static function fromString(?string $value): ?self
    {
        return new self($value);
    }

    public function getValue(): ?string
    {
        return $this->value;
    }
}
