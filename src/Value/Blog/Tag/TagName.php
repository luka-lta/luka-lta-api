<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Blog\Tag;

use LukaLtaApi\Exception\ApiInvalidArgumentException;

class TagName
{
    private function __construct(
        private readonly string $value,
    ) {
        if (empty(trim($value))) {
            throw new ApiInvalidArgumentException('Tag name cannot be empty.', 400);
        }

        if (mb_strlen($value) > 50) {
            throw new ApiInvalidArgumentException('Tag name must not exceed 50 characters.', 400);
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
