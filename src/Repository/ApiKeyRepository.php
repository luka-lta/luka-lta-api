<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\ApiKey\ApiKeyObject;
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
        } catch (PDOException) {
            throw new ApiDatabaseException(
                'Failed to create API key',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function loadAll(): ?array
    {
        $sql = <<<SQL
            SELECT id, origin, created_at, created_by, expires_at, api_key
            FROM api_keys
        SQL;

        try {
            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll();

            if (empty($rows)) {
                return null;
            }
        } catch (PDOException) {
            throw new ApiDatabaseException(
                'Failed to load API keys',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        return array_map(static fn($row) => ApiKeyObject::fromDatabase($row), $rows);
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
}
