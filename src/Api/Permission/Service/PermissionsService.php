<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Permission\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\PermissionRepository;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;

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
                JsonResult::from('No permissions found'),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        return ApiResult::from(
            JsonResult::from('Permissions fetched successfully', [
                'permissions' => $permissions->toArray()
            ])
        );
    }
}
