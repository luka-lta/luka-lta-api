<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\User;

use Countable;
use IteratorAggregate;
use JsonSerializable;

class Users implements IteratorAggregate, JsonSerializable, Countable
{
    private readonly array $users;

    public function __construct(User ...$users)
    {
        $this->users = $users;
    }

    public static function from(User ...$users): self
    {
        return new self(...$users);
    }

    public function getIterator(): \Generator
    {
        yield from $this->users;
    }

    public function count(): int
    {
        return count($this->users);
    }

    public function toArray(): array
    {
        return array_map(static fn($user) => $user->toArray(), $this->users);
    }

    public function jsonSerialize(): array
    {
        return $this->users;
    }
}
