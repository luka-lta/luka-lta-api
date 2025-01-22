<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\ApiKey\ApiKeyObject;
use LukaLtaApi\Value\ApiKey\KeyId;
use LukaLtaApi\Value\ApiKey\KeyOrigin;
use PDO;
use PDOException;

class ApiKeyRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(ApiKeyObject $keyObject): void
    {
        $sql = <<<SQL
            INSERT INTO api_keys (origin, created_at, created_by, expires_at, api_key)
            VALUES (:origin, :created_at, :created_by, :expires_at, :api_key)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'origin' => (string)$keyObject->getOrigin(),
                'created_at' => $keyObject->getCreatedAt()->format('Y-m-d H:i:s'),
                'created_by' => $keyObject->getCreatedBy()->asInt(),
                'expires_at' => $keyObject->getExpiresAt()?->format('Y-m-d H:i:s'),
                'api_key' => (string)$keyObject->getApiKey(),
            ]);

            $keyId = KeyId::fromString($this->pdo->lastInsertId());
        } catch (PDOException) {
            throw new ApiDatabaseException(
                'Failed to create API key',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        foreach ($keyObject->getPermissions() as $permission) {
            $this->addPermission($keyId, $permission);
        }
    }

    public function loadAll(): ?array
    {
        $sql = <<<SQL
        SELECT 
            ak.id AS api_key_id, ak.origin, ak.created_at, ak.created_by, ak.expires_at, ak.api_key,
            p.id AS permission_id, p.name AS permission_name, p.description AS permission_description
        FROM api_keys ak
        LEFT JOIN api_key_permissions akp ON ak.id = akp.api_key_id
        LEFT JOIN permissions p ON akp.permission_id = p.id
    SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                return null;
            }

            $groupedData = [];
            foreach ($rows as $row) {
                $apiKeyId = $row['api_key_id'];

                if (!isset($groupedData[$apiKeyId])) {
                    $groupedData[$apiKeyId] = [
                        'id' => $row['api_key_id'],
                        'origin' => $row['origin'],
                        'created_at' => $row['created_at'],
                        'created_by' => $row['created_by'],
                        'expires_at' => $row['expires_at'],
                        'api_key' => $row['api_key'],
                        'permissions' => [],
                    ];
                }

                if ($row['permission_id']) {
                    $groupedData[$apiKeyId]['permissions'][] = [
                        'id' => $row['permission_id'],
                        'name' => $row['permission_name'],
                        'description' => $row['permission_description'],
                    ];
                }
            }

            return array_map(static fn($data) => ApiKeyObject::fromDatabase($data), $groupedData);
        } catch (PDOException) {
            throw new ApiDatabaseException(
                'Failed to load API keys with permissions',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function getApiKeyByOrigin(KeyOrigin $origin): ?ApiKeyObject
    {
        $sql = <<<SQL
            SELECT id, origin, created_at, created_by, expires_at, api_key
            FROM api_keys
            WHERE origin = :origin
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['origin' => (string)$origin]);
            $row = $stmt->fetch();

            if ($row === false) {
                return null;
            }

            return ApiKeyObject::fromDatabase($row);
        } catch (PDOException) {
            throw new ApiDatabaseException(
                'Failed to get API key',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function hasPermission(KeyId $apiKeyId, string $permissionName): bool
    {
        $query = "
            SELECT COUNT(*) as count
            FROM api_key_permissions akp
            INNER JOIN permissions p ON akp.permission_id = p.id
            WHERE akp.api_key_id = :apiKeyId AND p.name = :permissionName
        ";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([
                'apiKeyId' => $apiKeyId->asInt(),
                'permissionName' => $permissionName,
            ]);
        } catch (PDOException) {
            throw new ApiDatabaseException(
                'Failed to check permission',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        return $stmt->fetchColumn() > 0;
    }

    public function addPermission(KeyId $apiKeyId, int $permissionId): void
    {
        $query = "INSERT INTO api_key_permissions (api_key_id, permission_id) VALUES (:apiKeyId, :permissionId)";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([
                'apiKeyId' => $apiKeyId->asInt(),
                'permissionId' => $permissionId,
            ]);
        } catch (PDOException) {
            throw new ApiDatabaseException(
                'Failed to add permission',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }
}
