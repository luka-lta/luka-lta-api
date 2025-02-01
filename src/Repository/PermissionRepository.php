<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Permission\Permission;
use LukaLtaApi\Value\Permission\Permissions;
use PDO;
use PDOException;

class PermissionRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getAvailablePermissions(): Permissions
    {
        $sql = <<<SQL
            SELECT *
            FROM permissions
        SQL;

        try {
            $stmt = $this->pdo->query($sql);


            $permissions = [];
            foreach ($stmt as $row) {
                $permissions[] = Permission::fromDatabase($row);
            }
        } catch (PDOException) {
            throw new ApiDatabaseException(
                'Failed to load permissions',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        return Permissions::fromObjects(...$permissions);
    }
}
