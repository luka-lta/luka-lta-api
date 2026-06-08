<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository\Contracts;

use LukaLtaApi\Value\Permission\Permissions;

interface PermissionRepositoryInterface
{
    public function getAvailablePermissions(): Permissions;
}
