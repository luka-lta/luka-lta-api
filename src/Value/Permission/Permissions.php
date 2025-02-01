<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Permission;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class Permissions implements IteratorAggregate, JsonSerializable, Countable
{
    private readonly array $permissions;

    private function __construct(Permission ...$permissions)
    {
        $this->permissions = $permissions;
    }

    public static function from(array ...$permissions): self
    {
        $permissionsList = [];

        foreach ($permissions as $permission) {
            if ($permission['permission_id'] === null) {
                $permission[] = [];
                continue;
            }

            $permissionsList[] = Permission::fromDatabase($permission);
        }

        return new self(...$permissionsList);
    }

    public static function fromObjects(Permission ...$permissions): self
    {
        return new self(...$permissions);
    }

    public function getIterator(): Generator
    {
        yield from $this->permissions;
    }

    public function toArray(): array
    {
        return array_map(static fn($permission) => $permission->toArray(), $this->permissions);
    }

    public function jsonSerialize(): array
    {
        return $this->permissions;
    }

    public function count(): int
    {
        return count($this->permissions);
    }
}
