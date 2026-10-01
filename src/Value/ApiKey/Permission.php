<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\ApiKey;

use JsonSerializable;

class Permission implements JsonSerializable
{
    private function __construct(
        private readonly int    $permissionId,
        private readonly string $name,
        private readonly string $description,
    ) {
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            (int) $row['permission_id'],
            $row['permission_name'],
            $row['permission_description'],
        );
    }

    public function getPermissionId(): int
    {
        return $this->permissionId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function jsonSerialize(): array
    {
        return [
            'id'          => $this->permissionId,
            'name'        => $this->name,
            'description' => $this->description,
        ];
    }
}
