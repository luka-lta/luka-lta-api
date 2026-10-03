<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project;

use LukaLtaApi\Exception\ApiValidationException;
use Ramsey\Uuid\Uuid;

class ProjectId
{
    private function __construct(
        private readonly string $value,
    ) {
        if (!Uuid::isValid($value)) {
            throw new ApiValidationException('Project ID must be a valid UUID.', 400);
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function asString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
