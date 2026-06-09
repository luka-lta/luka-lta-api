<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Blog\Tag;

class TagSlug
{
    private function __construct(
        private readonly string $value,
    ) {
    }

    public static function fromName(string $name): self
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($name)));
        $slug = trim($slug, '-');

        return new self($slug);
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
