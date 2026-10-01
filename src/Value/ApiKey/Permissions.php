<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\ApiKey;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class Permissions implements Countable, IteratorAggregate, JsonSerializable
{
    private readonly array $permissions;

    private function __construct(Permission ...$permissions)
    {
        $this->permissions = $permissions;
    }

    public static function from(Permission ...$permissions): self
    {
        return new self(...$permissions);
    }

    public function hasName(string $name): bool
    {
        foreach ($this->permissions as $permission) {
            if ($permission->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    public function getIterator(): Generator
    {
        yield from $this->permissions;
    }

    public function count(): int
    {
        return count($this->permissions);
    }

    public function jsonSerialize(): array
    {
        return $this->permissions;
    }
}
