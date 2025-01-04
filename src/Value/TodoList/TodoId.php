<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\TodoList;

use LukaLtaApi\Value\IdentifierInterface;

class TodoId implements IdentifierInterface
{
    private function __construct(
        private readonly int $id
    ) {
    }

    public static function fromInt(int $id): self
    {
        return new self($id);
    }

    public static function fromString(string $id): self
    {
        return new self((int) $id);
    }

    public function asString(): string
    {
        return (string) $this->id;
    }

    public function asInt(): int
    {
        return $this->id;
    }
}
