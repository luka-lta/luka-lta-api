<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository\Contracts;

use LukaLtaApi\Api\ApiKey\Value\ApiKeyExtraFilter;
use LukaLtaApi\Value\ApiKey\ApiKeyObject;
use LukaLtaApi\Value\ApiKey\ApiKeyObjects;
use LukaLtaApi\Value\ApiKey\KeyId;
use LukaLtaApi\Value\ApiKey\KeyOrigin;
use LukaLtaApi\Value\Permission\Permissions;

interface ApiKeyRepositoryInterface
{
    public function create(ApiKeyObject $keyObject): void;

    public function loadAll(ApiKeyExtraFilter $filter): ApiKeyObjects;

    public function getApiKeyByOrigin(KeyOrigin $origin): ?ApiKeyObject;

    public function hasPermission(KeyId $apiKeyId, int $permissionId): bool;

    public function addPermissions(KeyId $apiKeyId, Permissions $permissions): void;
}
