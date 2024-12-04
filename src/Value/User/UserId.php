<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\User;

use LukaLtaApi\Value\IdentifierInterface;

class UserId implements IdentifierInterface
{
    private function __construct(
        private readonly int $userId,
    ) {
    }

    public static function fromInt(int $userId): self
    {
        return new self($userId);
    }

    public static function fromString(string $userId): self
    {
        return new self((int) $userId);
    }

    public function asString(): string
    {
        return (string)$this->userId;
    }

    public function asInt(): int
    {
        return $this->userId;
    }
}
