<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\TodoList;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiValidationException;

class TodoTitle
{
    private function __construct(
        private readonly string $title
    ) {
        if (empty($title)) {
            throw new ApiValidationException('Title cannot be empty', StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        if (strlen($title) > 255) {
            throw new ApiValidationException(
                'Title cannot be longer than 255 characters',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }
    }

    public static function fromString(string $title): self
    {
        return new self($title);
    }

    public function toString(): string
    {
        return $this->title;
    }
}
