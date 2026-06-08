<?php

declare(strict_types=1);

namespace LukaLtaApi\Service\Contracts;

use LukaLtaApi\Value\ApiKey\KeyId;

interface PermissionServiceInterface
{
    public function hasAccess(KeyId $apiKeyId, array $requiredPermissions): bool;
}
