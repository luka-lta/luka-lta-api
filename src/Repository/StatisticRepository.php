<?php

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Stats\AbstractStat;
use LukaLtaApi\Value\Stats\StatsCollection;
use PDO;
use PDOException;

class StatisticRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getStats(string $rowName, string $label, string $statClass): StatsCollection
    {
        $sql = <<<SQL
            SELECT
                COALESCE(NULLIF($rowName, ''), 'Unknown') AS label,
                COUNT(*) AS amount,
                ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER (), 0) AS percentage
            FROM url_clicks
            GROUP BY COALESCE(NULLIF($rowName, ''), 'Unknown')
            ORDER BY amount DESC;
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            $stats = [];
            foreach ($stmt as $row) {
                /** @var AbstractStat $statClass */
                $stats[] = $statClass::from(
                    $label,
                    $row['label'],
                    $row['amount'],
                    $row['percentage']
                );
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Database error: ' . $exception->getMessage(),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }

        return StatsCollection::from(...$stats);
    }
}
