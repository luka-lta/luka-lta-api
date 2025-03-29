<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Roles\Service;

use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use PermissionsModule\Service\RoleService;

class RolesService
{
    public function __construct(
        private readonly RoleService $service,
    ) {
    }

    public function getAvailableRoles(): ApiResult
    {
        $roles = $this->service->getAvailableRoles();

        if ($roles->count() === 0) {
            return ApiResult::from(
                JsonResult::from('No roles found', ['roles' => []])
            );
        }

        return ApiResult::from(
            JsonResult::from('Roles found', ['roles' => $roles->toArray()])
        );
    }
}
