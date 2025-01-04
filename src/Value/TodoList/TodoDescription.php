<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\TodoList;

class TodoDescription
{
    private function __construct(
        private readonly ?string $description
    ) {
    }

    public static function fromString(?string $description): self
    {
        return new self($description);
    }

    public function toString(): ?string
    {
        return $this->description;
    }
}
