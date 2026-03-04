<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Filter\FilterQueryBuilder;
use PDO;
use PDOException;

class SiteMetricRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getSiteMetricData(string $sql, array $params = []): array
    {
        try {
            $placeholderCount = substr_count($sql, '?');
            $paramCount       = count($params);
            $repeatTimes      = $paramCount > 0 ? intdiv($placeholderCount, $paramCount) : 1;
            $repeatedParams   = array_merge(...array_fill(0, $repeatTimes, $params));

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($repeatedParams);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Database query failed: ' . $exception->getMessage(),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }
}
