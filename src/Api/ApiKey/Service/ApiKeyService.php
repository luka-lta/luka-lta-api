<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\ApiKey\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Api\ApiKey\Value\ApiKeyExtraFilter;
use LukaLtaApi\Repository\ApiKeyRepository;
use LukaLtaApi\Repository\PermissionRepository;
use LukaLtaApi\Value\ApiKey\ApiKeyObject;
use LukaLtaApi\Value\Permission\Permission;
use LukaLtaApi\Value\Permission\Permissions;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ServerRequestInterface;

class ApiKeyService
{
    public function __construct(
        private readonly ApiKeyRepository     $repository,
        private readonly PermissionRepository $permissionRepository,
    ) {
    }

    public function create(ServerRequestInterface $request): ApiResult
    {
        $body = $request->getParsedBody();
        $createdBy = (int)$request->getAttribute('userId');
        $keyOrigin = $body['origin'];
        $expiresAt = $body['expiresAt'] ?? null;
        $permissions = $body['permissions'] ?? [];

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

        return ApiResult::from(
            JsonResult::from('API key created successfully', ['apiKey' => $apiKey->toArray()])
        );
    }

    public function getAllKeys(ServerRequestInterface $request): ApiResult
    {
        $filter = ApiKeyExtraFilter::parseFromQuery($request->getQueryParams());
        $apiKeys = $this->repository->loadAll($filter);

        if ($apiKeys->count() === 0) {
            return ApiResult::from(
                JsonResult::from('No API keys found', ['apiKeys' => []]),
            );
        }

        return ApiResult::from(
            JsonResult::from('API keys fetched successfully', ['apiKeys' => $apiKeys->toArray()])
        );
    }
}
