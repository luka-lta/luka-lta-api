<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use Latitude\QueryBuilder\QueryFactory;
use LukaLtaApi\Api\ApiKey\Value\ApiKeyExtraFilter;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\ApiKey\ApiKeyObject;
use LukaLtaApi\Value\ApiKey\ApiKeyObjects;
use LukaLtaApi\Value\ApiKey\KeyId;
use LukaLtaApi\Value\ApiKey\KeyOrigin;
use LukaLtaApi\Value\Permission\Permission;
use LukaLtaApi\Value\Permission\Permissions;
use PDO;
use PDOException;

use function Latitude\QueryBuilder\alias;
use function Latitude\QueryBuilder\express;
use function Latitude\QueryBuilder\identify;
use function Latitude\QueryBuilder\on;

class ApiKeyRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly QueryFactory $queryFactory,
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

        $this->addPermissions($keyId, $keyObject->getPermissions());
    }

    public function loadAll(ApiKeyExtraFilter $filter): ApiKeyObjects
    {
        $select = $this->queryFactory
            ->select(
                'ak.key_id',
                'ak.origin',
                'ak.created_at',
                'ak.created_by',
                'ak.expires_at',
                'ak.api_key',
                express(
                    'COALESCE(
                NULLIF(
                    JSON_ARRAYAGG(
                        JSON_OBJECT(
                            \'permission_id\', %s,
                            \'permission_name\', %s,
                            \'permission_description\', %s
                        )
                    ),
                    JSON_ARRAY(NULL)
                ),
                JSON_ARRAY()
            ) AS permissions',
                    identify('p.permission_id'),
                    identify('p.permission_name'),
                    identify('p.permission_description')
                )
            )
            ->from(alias('api_keys', 'ak'))
            ->leftJoin(alias('api_key_permissions', 'akp'), on('ak.key_id', 'akp.api_key_id'))
            ->leftJoin(alias('permissions', 'p'), on('akp.permission_id', 'p.permission_id'))
            ->groupBy('ak.key_id');

        $query = $filter->createSqlFilter($select);
        $sql = $query->compile();

        try {
            $stmt = $this->pdo->prepare($sql->sql());
            $stmt->execute($sql->params());

            $keyObjects = [];
            foreach ($stmt as $row) {
                $keyObjects[] = ApiKeyObject::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to load API keys with permissions',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }

        return ApiKeyObjects::from(...$keyObjects);
    }


    public function getApiKeyByOrigin(KeyOrigin $origin): ?ApiKeyObject
    {
        $sql = <<<SQL
            SELECT 
                ak.key_id, 
                ak.origin, 
                ak.created_at, 
                ak.created_by, 
                ak.expires_at, 
                ak.api_key,
                COALESCE(
                NULLIF(
                    JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'permission_id', p.permission_id,
                            'permission_name', p.permission_name,
                            'permission_description', p.permission_description
                        )
                    ),
                JSON_ARRAY(NULL)
                ),
                JSON_ARRAY()
                ) AS permissions
            FROM api_keys ak
            LEFT JOIN api_key_permissions akp ON ak.key_id = akp.api_key_id
            LEFT JOIN permissions p ON akp.permission_id = p.permission_id
            WHERE ak.origin = :origin
            GROUP BY ak.key_id
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

    public function hasPermission(KeyId $apiKeyId, int $permissionId): bool
    {
        $query = "
            SELECT COUNT(*) as count
            FROM api_key_permissions akp
            INNER JOIN permissions p ON akp.permission_id = p.permission_id
            WHERE akp.api_key_id = :apiKeyId AND p.permission_id = :permissionId
        ";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([
                'apiKeyId' => $apiKeyId->asInt(),
                'permissionId' => $permissionId,
            ]);
        } catch (PDOException) {
            throw new ApiDatabaseException(
                'Failed to check permission',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        return $stmt->fetchColumn() > 0;
    }

    public function addPermissions(KeyId $apiKeyId, Permissions $permissions): void
    {
        $query = "INSERT INTO api_key_permissions (api_key_id, permission_id) VALUES (:apiKeyId, :permissionId)";

        try {
            /** @var Permission $permission */
            foreach ($permissions as $permission) {
                $stmt = $this->pdo->prepare($query);
                $stmt->execute([
                    'apiKeyId' => $apiKeyId->asInt(),
                    'permissionId' => $permission->getPermissionId(),
                ]);
            }
        } catch (PDOException) {
            throw new ApiDatabaseException(
                'Failed to add permission',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }
}
