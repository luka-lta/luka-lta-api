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
        return $this->permissionRepository->getAvailablePermissions();
    }
}
