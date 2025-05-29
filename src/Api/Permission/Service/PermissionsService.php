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

        return ApiResult::from(
            JsonResult::from('Permissions fetched successfully', [
                'permissions' => $permissions->toArray()
            ])
        );
    }
}
