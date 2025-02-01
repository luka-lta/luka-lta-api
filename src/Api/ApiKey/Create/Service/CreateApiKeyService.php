<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\ApiKey\Create\Service;

use LukaLtaApi\Repository\ApiKeyRepository;
use LukaLtaApi\Repository\PermissionRepository;
use LukaLtaApi\Value\ApiKey\ApiKeyObject;
use LukaLtaApi\Value\Permission\Permission;
use LukaLtaApi\Value\Permission\Permissions;

class CreateApiKeyService
{
    public function __construct(
        private readonly ApiKeyRepository $repository,
        private readonly PermissionRepository $permissionRepository,
    ) {
    }

    public function create(
        string $keyOrigin,
        int $createdBy,
        ?string $expiresAt,
        array $permissions,
    ): ApiKeyObject {
        $availablePermissions = $this->permissionRepository->getAvailablePermissions();
        $keyPermissions = [];

        /** @var Permission $permission */
        foreach ($availablePermissions as $permission) {
            $permissionId = $permission->getPermissionId();

            if (in_array($permissionId, $permissions, true)) {
                $keyPermissions[] = $permission;
            }
        }

        $apiKey = ApiKeyObject::create(
            $keyOrigin,
            $createdBy,
            date('Y-m-d H:i:s'),
            $expiresAt,
            Permissions::fromObjects(...$keyPermissions),
        );

        $this->repository->create($apiKey);

        return $apiKey;
    }
}
