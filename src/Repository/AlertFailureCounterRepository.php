<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use DateTimeImmutable;
use LukaLtaApi\Exception\ApiDatabaseException;
use PDO;
use PDOException;

class AlertFailureCounterRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function incrementAndGet(string $fingerprint, DateTimeImmutable $now): int
    {
        $sql = <<<SQL
            INSERT INTO alert_failure_counters (fingerprint, consecutive_count, updated_at)
            VALUES (:fingerprint, 1, :updated_at)
            ON DUPLICATE KEY UPDATE consecutive_count = consecutive_count + 1, updated_at = :updated_at
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'fingerprint' => $fingerprint,
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);

            $selectStmt = $this->pdo->prepare(
                'SELECT consecutive_count FROM alert_failure_counters WHERE fingerprint = :fingerprint',
            );
            $selectStmt->execute(['fingerprint' => $fingerprint]);

            return (int) $selectStmt->fetchColumn();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to update failure counter.', previous: $exception);
        }
    }

    public function reset(string $fingerprint): void
    {
        $sql = 'DELETE FROM alert_failure_counters WHERE fingerprint = :fingerprint';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['fingerprint' => $fingerprint]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to reset failure counter.', previous: $exception);
        }
    }

    public function purgeStaleOlderThan(DateTimeImmutable $cutoff): int
    {
        $sql = 'DELETE FROM alert_failure_counters WHERE updated_at < :cutoff';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s')]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to purge stale failure counters.', previous: $exception);
        }

        return $stmt->rowCount();
    }
}
