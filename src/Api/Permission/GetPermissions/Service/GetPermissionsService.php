<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Permission\GetPermissions\Service;

use LukaLtaApi\Repository\PermissionRepository;

class GetPermissionsService
{
    public function __construct(
        private readonly PermissionRepository $permissionRepository
    ) {
    }

    public function getPermissions(): ?array
    {
        $permissions = $this->permissionRepository->getAvailablePermissions();

        if ($permissions->count() === 0) {
            return null;
        }

        return $permissions->toArray();
    }
}
