<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository\Traits;

use LukaLtaApi\Exception\ApiDatabaseException;
use PDO;
use PDOException;

trait TransactionTrait
{
    public function executeInTransaction(callable $fn): mixed
    {
        /** @var PDO $pdo */
        $pdo = $this->pdo;
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw new ApiDatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
