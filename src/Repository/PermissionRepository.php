<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use PDO;
use PDOException;

class PermissionRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getAvailablePermissions(): ?array
    {
        $sql = <<<SQL
            SELECT *
            FROM permissions
        SQL;

        try {
            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll();

            if (empty($rows)) {
                return null;
            }

            return $rows;
        } catch (PDOException) {
            throw new ApiDatabaseException(
                'Failed to load permissions',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }
}
