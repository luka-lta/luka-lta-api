<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use PDO;
use PDOException;

class SiteMetricRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getSiteMetricData(string $query): array
    {
        try {
            $stmt = $this->pdo->query($query);
            if ($stmt === false) {
                throw new ApiDatabaseException('Failed to execute query: ' . $query);
            }
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
