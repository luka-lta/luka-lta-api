<?php

declare(strict_types=1);

namespace LukaLtaApi\Service;

use LukaLtaApi\Repository\ApiKeyRepository;
use LukaLtaApi\Value\ApiKey\KeyId;

class PermissionService
{
    public function __construct(
        private readonly ApiKeyRepository $repository
    ) {
    }

    public function hasAccess(KeyId $apiKeyId, array $requiredPermissions): bool
    {
        foreach ($requiredPermissions as $permission) {
            if (!$this->repository->hasPermission($apiKeyId, $permission)) {
                return false;
            }
        }

        return true;
    }
}
