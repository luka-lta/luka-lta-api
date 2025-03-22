<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Permission;

class Permission
{
    public const int CREATE_LINKS = 1;
    public const int DELETE_LINKS = 2;
    public const int EDIT_LINKS = 3;
    public const int VIEW_LINKS = 4;
    public const int VIEW_CLICKS = 5;
    public const int CREATE_API_KEYS = 6;
    public const int READ_API_KEYS = 7;
    public const int READ_PERMISSIONS = 8;

    private function __construct(
        private readonly ?int $permissionId,
        private readonly string $name,
        private readonly string $description,
    ) {
    }

    public static function create(
        string $name,
        string $description
    ): self {
        return new self(null, $name, $description);
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            (int)$row['permission_id'],
            $row['permission_name'],
            $row['permission_description'],
        );
    }

    public function toArray(): array
    {
        return [
            'permissionId' => $this->permissionId,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }

    public function getPermissionId(): ?int
    {
        return $this->permissionId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
