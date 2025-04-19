<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Permission\Service;

use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use PermissionsModule\Repository\PermissionRepository;

class PermissionsService
{
    public function __construct(
        private readonly PermissionRepository $permissionRepository
    ) {
    }

    public function getPermissions(): ApiResult
    {
        $permissions = $this->permissionRepository->getAvailablePermissions();

        if ($permissions->count() === 0) {
            return ApiResult::from(
                JsonResult::from('No permissions found', ['permissions' => []]),
            );
        }
        // TODO: Fix das alle permissions angezeigt werden anstatt eine, fix das die user endpoints korrekt funktionieren
        return ApiResult::from(
            JsonResult::from('Permissions fetched successfully', [
                'permissions' => $permissions->toArray()
            ])
        );
    }
}
