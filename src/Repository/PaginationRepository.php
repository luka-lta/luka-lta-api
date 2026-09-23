<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use Latitude\QueryBuilder\QueryFactory;
use LukaLtaApi\Exception\ApiDatabaseException;
use PDO;
use PDOException;
use function Latitude\QueryBuilder\field;

class PaginationRepository
{
    public function __construct(
        private readonly QueryFactory $queryFactory,
        private readonly PDO $pdo,
    ) {
    }

    public function getDataCount(string $table, array $where = []): int
    {
        $countQuery = $this->queryFactory->select('COUNT(*) as total')->from($table);

        foreach ($where as $field => $value) {
            $countQuery->where(field($field)->eq($value));
        }

        $countSql = $countQuery->compile();

        try {
            $countStatement = $this->pdo->prepare($countSql->sql());
            $countStatement->execute($countSql->params());
            $totalCount = (int) $countStatement->fetchColumn();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                $exception->getMessage(),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
        return $totalCount;
    }
}
