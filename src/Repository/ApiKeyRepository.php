<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use DateTimeImmutable;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\ApiKey\ApiKey;
use LukaLtaApi\Value\ApiKey\ApiKeyId;
use LukaLtaApi\Value\ApiKey\ApiKeys;
use LukaLtaApi\Value\ApiKey\Permission;
use LukaLtaApi\Value\ApiKey\Permissions;
use LukaLtaApi\Value\User\UserId;
use PDO;
use PDOException;

class ApiKeyRepository
{
    private const API_KEY_SELECT = <<<SQL
        SELECT key_id, label, origin, key_suffix, created_by, created_at, expires_at
        FROM api_keys
    SQL;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(
        string $label,
        string $origin,
        string $hashedKey,
        string $keySuffix,
        UserId $createdBy,
        ?DateTimeImmutable $expiresAt,
    ): ApiKeyId {
        $sql = <<<SQL
            INSERT INTO api_keys (label, origin, api_key, key_suffix, created_by, expires_at)
            VALUES (:label, :origin, :api_key, :key_suffix, :created_by, :expires_at)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'label'      => $label,
                'origin'     => $origin,
                'api_key'    => $hashedKey,
                'key_suffix' => $keySuffix,
                'created_by' => $createdBy->asInt(),
                'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            ]);

            return ApiKeyId::fromInt((int) $this->pdo->lastInsertId());
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to create API key.', previous: $exception);
        }
    }

    public function loadAll(): ApiKeys
    {
        $sql = self::API_KEY_SELECT . ' ORDER BY created_at DESC';

        try {
            $stmt = $this->pdo->query($sql);

            $apiKeys = [];
            foreach ($stmt as $row) {
                $apiKeys[] = ApiKey::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch API keys.', previous: $exception);
        }

        return ApiKeys::from(...$apiKeys);
    }

    public function loadById(ApiKeyId $keyId): ?ApiKey
    {
        $sql = self::API_KEY_SELECT . ' WHERE key_id = :key_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['key_id' => $keyId->asInt()]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch API key.', previous: $exception);
        }

        if ($row === false) {
            return null;
        }

        return ApiKey::fromDatabase($row);
    }

    public function findActiveByHashedKey(string $hashedKey): ?ApiKey
    {
        $sql = self::API_KEY_SELECT . ' WHERE api_key = :api_key AND (expires_at IS NULL OR expires_at > NOW())';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['api_key' => $hashedKey]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to look up API key.', previous: $exception);
        }

        if ($row === false) {
            return null;
        }

        return ApiKey::fromDatabase($row);
    }

    public function delete(ApiKeyId $keyId): void
    {
        $sql = 'DELETE FROM api_keys WHERE key_id = :key_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['key_id' => $keyId->asInt()]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to delete API key.', previous: $exception);
        }
    }

    public function attachPermissions(ApiKeyId $keyId, array $permissionIds): void
    {
        if (empty($permissionIds)) {
            return;
        }

        $sql = <<<SQL
            INSERT IGNORE INTO api_key_permissions (api_key_id, permission_id)
            VALUES (:api_key_id, :permission_id)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($permissionIds as $permissionId) {
                $stmt->execute([
                    'api_key_id'    => $keyId->asInt(),
                    'permission_id' => (int) $permissionId,
                ]);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to attach permissions.', previous: $exception);
        }
    }

    public function loadPermissionsFor(ApiKeyId $keyId): Permissions
    {
        $sql = <<<SQL
            SELECT p.permission_id, p.permission_name, p.permission_description
            FROM permissions p
            INNER JOIN api_key_permissions akp ON p.permission_id = akp.permission_id
            WHERE akp.api_key_id = :api_key_id
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['api_key_id' => $keyId->asInt()]);

            $permissions = [];
            foreach ($stmt as $row) {
                $permissions[] = Permission::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch permissions for API key.', previous: $exception);
        }

        return Permissions::from(...$permissions);
    }

    public function hasPermission(ApiKeyId $keyId, string $permissionName): bool
    {
        $sql = <<<SQL
            SELECT 1
            FROM api_key_permissions akp
            INNER JOIN permissions p ON p.permission_id = akp.permission_id
            WHERE akp.api_key_id = :api_key_id AND p.permission_name = :permission_name
            LIMIT 1
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'api_key_id'      => $keyId->asInt(),
                'permission_name' => $permissionName,
            ]);

            return $stmt->fetch() !== false;
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to check API key permission.', previous: $exception);
        }
    }
}
