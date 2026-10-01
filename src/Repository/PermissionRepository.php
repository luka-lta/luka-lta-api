<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\ApiKey\Permission;
use LukaLtaApi\Value\ApiKey\Permissions;
use PDO;
use PDOException;

class PermissionRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function loadAll(): Permissions
    {
        $sql = <<<SQL
            SELECT permission_id, permission_name, permission_description
            FROM permissions
            ORDER BY permission_name ASC
        SQL;

        try {
            $stmt = $this->pdo->query($sql);

            $permissions = [];
            foreach ($stmt as $row) {
                $permissions[] = Permission::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch permissions.', previous: $exception);
        }

        return Permissions::from(...$permissions);
    }
}
