<?php

namespace LukaLtaApi\Value\LinkCollection;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiInvalidArgumentException;

class IconName
{
    private function __construct(
        private readonly ?string $value
    ) {
        if ($value === null) {
            return;
        }

        if (empty($value)) {
            throw new ApiInvalidArgumentException(
                'Icon name must not be empty',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        if (!preg_match('/^[A-Za-z0-9_-]{3,50}$/', $value)) {
            throw new ApiInvalidArgumentException(
                'Icon name must be between 3 and 50 characters long and only contain
                 letters,
                 numbers, underscores and hyphens',
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
