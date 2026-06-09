<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Blog;

use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Value\IdentifierInterface;
use Ramsey\Uuid\Uuid;

class BlogId implements IdentifierInterface
{
    private function __construct(
        private readonly string $value,
    ) {
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        if (empty($value)) {
            throw new ApiValidationException('Blog ID cannot be empty.', 400);
        }

        return new self($value);
    }

    public function asString(): string
    {
        return $this->value;
    }

    public function asInt(): int
    {
        throw new \RuntimeException('BlogId is UUID-based and cannot be cast to int.');
    }
}
