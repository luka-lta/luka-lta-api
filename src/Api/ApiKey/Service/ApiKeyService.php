<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\ApiKey\Service;

use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiKeyNotFoundException;
use LukaLtaApi\Repository\ApiKeyRepository;
use LukaLtaApi\Repository\PermissionRepository;
use LukaLtaApi\Value\ApiKey\ApiKeyId;
use LukaLtaApi\Value\User\UserId;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;

class ApiKeyService
{
    /** Random token length in bytes before hex-encoding (64 hex chars). */
    private const TOKEN_BYTES = 32;

    private const TOKEN_PREFIX = 'lta_';

    public function __construct(
        private readonly ApiKeyRepository     $apiKeyRepository,
        private readonly PermissionRepository $permissionRepository,
    ) {
    }

    public function createApiKey(array $data, int $createdByUserId): ApiResult
    {
        $plainKey  = self::TOKEN_PREFIX . bin2hex(random_bytes(self::TOKEN_BYTES));
        $hashedKey = hash('sha256', $plainKey);
        $keySuffix = substr($plainKey, -4);

        $expiresAt = isset($data['expiresAt']) ? new DateTimeImmutable($data['expiresAt']) : null;

        $keyId = $this->apiKeyRepository->create(
            $data['label'],
            $data['origin'],
            $hashedKey,
            $keySuffix,
            UserId::fromInt($createdByUserId),
            $expiresAt,
        );

        $this->apiKeyRepository->attachPermissions($keyId, $data['permissionIds'] ?? []);

        $apiKey = $this->apiKeyRepository->loadById($keyId);
        $apiKey->setPermissions($this->apiKeyRepository->loadPermissionsFor($keyId));

        return ApiResult::from(
            JsonResult::from('API key created.', [
                'apiKey' => $apiKey,
                'plainKey' => $plainKey,
            ]),
            StatusCodeInterface::STATUS_CREATED,
        );
    }

    public function listApiKeys(): ApiResult
    {
        $apiKeys = $this->apiKeyRepository->loadAll();

        foreach ($apiKeys as $apiKey) {
            $apiKey->setPermissions($this->apiKeyRepository->loadPermissionsFor($apiKey->getKeyId()));
        }

        return ApiResult::from(
            JsonResult::from('API keys fetched.', ['apiKeys' => $apiKeys])
        );
    }

    public function deleteApiKey(ApiKeyId $keyId): ApiResult
    {
        if ($this->apiKeyRepository->loadById($keyId) === null) {
            throw new ApiKeyNotFoundException();
        }

        $this->apiKeyRepository->delete($keyId);

        return ApiResult::from(
            JsonResult::from('API key deleted.'),
            StatusCodeInterface::STATUS_NO_CONTENT,
        );
    }

    public function listPermissions(): ApiResult
    {
        return ApiResult::from(
            JsonResult::from('Permissions fetched.', ['permissions' => $this->permissionRepository->loadAll()])
        );
    }
}
