<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project\Tag;

use LukaLtaApi\Exception\ApiInvalidArgumentException;

class ProjectTagSlug
{
    private function __construct(
        private readonly string $value,
    ) {
        if ($value === '') {
            throw new ApiInvalidArgumentException('Project tag slug cannot be empty.', 400);
        }
    }

    public static function fromName(string $name): self
    {
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', trim($name)));

        return new self(trim($slug, '-'));
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
