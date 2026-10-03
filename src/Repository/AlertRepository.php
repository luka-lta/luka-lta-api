<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use DateTimeImmutable;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Alert\Alert;
use LukaLtaApi\Value\Alert\Alerts;
use LukaLtaApi\Value\User\UserId;
use PDO;
use PDOException;

class AlertRepository
{
    private const string SELECT_COLUMNS = 'alert_id, fingerprint, source, source_id, type, severity, title, '
        . 'description, context, occurrence_count, first_occurred_at, last_occurred_at, resolved_at';

    private const string SELECT_COLUMNS_ALIASED = 'a.alert_id, a.fingerprint, a.source, a.source_id, a.type, '
        . 'a.severity, a.title, a.description, a.context, a.occurrence_count, a.first_occurred_at, '
        . 'a.last_occurred_at, a.resolved_at';

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function loadActive(?string $source = null, int $limit = 50): Alerts
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS . ' FROM alerts
            WHERE resolved_at IS NULL
              AND (:source IS NULL OR source = :source)
            ORDER BY last_occurred_at DESC
            LIMIT :limit';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue('source', $source);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $alerts = [];
            foreach ($stmt as $row) {
                $alerts[] = Alert::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch alerts.', previous: $exception);
        }

        return Alerts::from(...$alerts);
    }

    public function loadFeed(UserId $userId, int $limit, int $offset): array
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS_ALIASED . ', (notification_reads.alert_id IS NOT NULL) AS is_read
            FROM alerts a
            LEFT JOIN notification_reads
                ON notification_reads.alert_id = a.alert_id AND notification_reads.user_id = :user_id
            ORDER BY a.last_occurred_at DESC
            LIMIT :limit OFFSET :offset';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue('user_id', $userId->asInt(), PDO::PARAM_INT);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $feed = [];
            foreach ($stmt as $row) {
                $feed[] = [
                    'alert' => Alert::fromDatabase($row),
                    'isRead' => (bool) $row['is_read'],
                ];
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch notification feed.', previous: $exception);
        }

        return $feed;
    }

    public function countUnread(UserId $userId): int
    {
        $sql = <<<SQL
            SELECT COUNT(*) AS unread_count
            FROM alerts a
            WHERE resolved_at IS NULL
              AND NOT EXISTS (
                  SELECT 1 FROM notification_reads
                  WHERE notification_reads.alert_id = a.alert_id AND notification_reads.user_id = :user_id
              )
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['user_id' => $userId->asInt()]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to count unread notifications.', previous: $exception);
        }
    }

    public function findActiveByFingerprint(string $fingerprint): ?Alert
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS . '
            FROM alerts
            WHERE resolved_at IS NULL AND fingerprint = :fingerprint
            LIMIT 1';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['fingerprint' => $fingerprint]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to look up alert.', previous: $exception);
        }

        return $row !== false ? Alert::fromDatabase($row) : null;
    }

    public function findById(string $alertId): ?Alert
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS . ' FROM alerts WHERE alert_id = :alert_id LIMIT 1';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['alert_id' => $alertId]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to look up alert.', previous: $exception);
        }

        return $row !== false ? Alert::fromDatabase($row) : null;
    }

    public function create(Alert $alert): void
    {
        $sql = <<<SQL
            INSERT INTO alerts (
                alert_id, fingerprint, source, source_id, type, severity, title, description,
                context, occurrence_count, first_occurred_at, last_occurred_at, resolved_at
            ) VALUES (
                :alert_id, :fingerprint, :source, :source_id, :type, :severity, :title, :description,
                :context, :occurrence_count, :first_occurred_at, :last_occurred_at, :resolved_at
            )
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'alert_id' => $alert->getAlertId(),
                'fingerprint' => $alert->getFingerprint(),
                'source' => $alert->getSource(),
                'source_id' => $alert->getSourceId(),
                'type' => $alert->getType(),
                'severity' => $alert->getSeverity(),
                'title' => $alert->getTitle(),
                'description' => $alert->getDescription(),
                'context' => json_encode($alert->getContext(), JSON_THROW_ON_ERROR),
                'occurrence_count' => $alert->getOccurrenceCount(),
                'first_occurred_at' => $alert->getFirstOccurredAt()->format('Y-m-d H:i:s'),
                'last_occurred_at' => $alert->getLastOccurredAt()->format('Y-m-d H:i:s'),
                'resolved_at' => $alert->getResolvedAt()?->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to store alert.', previous: $exception);
        }
    }

    public function bumpOccurrence(
        string            $alertId,
        string            $description,
        array             $context,
        DateTimeImmutable $now,
    ): void {
        $sql = <<<SQL
            UPDATE alerts
            SET occurrence_count = occurrence_count + 1,
                description = :description,
                context = :context,
                last_occurred_at = :last_occurred_at
            WHERE alert_id = :alert_id
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'alert_id' => $alertId,
                'description' => $description,
                'context' => json_encode($context, JSON_THROW_ON_ERROR),
                'last_occurred_at' => $now->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to bump alert occurrence.', previous: $exception);
        }
    }

    public function resolve(string $alertId, DateTimeImmutable $resolvedAt): void
    {
        $sql = 'UPDATE alerts SET resolved_at = :resolved_at WHERE alert_id = :alert_id AND resolved_at IS NULL';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'alert_id' => $alertId,
                'resolved_at' => $resolvedAt->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to resolve alert.', previous: $exception);
        }
    }

    public function markRead(UserId $userId, string $alertId): void
    {
        $sql = <<<SQL
            INSERT INTO notification_reads (user_id, alert_id, read_at)
            VALUES (:user_id, :alert_id, NOW())
            ON DUPLICATE KEY UPDATE read_at = NOW()
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'user_id' => $userId->asInt(),
                'alert_id' => $alertId,
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to mark notification as read.', previous: $exception);
        }
    }

    public function markAllRead(UserId $userId): void
    {
        $sql = <<<SQL
            INSERT INTO notification_reads (user_id, alert_id, read_at)
            SELECT :user_id, alert_id, NOW() FROM alerts WHERE resolved_at IS NULL
            ON DUPLICATE KEY UPDATE read_at = NOW()
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['user_id' => $userId->asInt()]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to mark all notifications as read.', previous: $exception);
        }
    }

    public function purgeResolvedOlderThan(DateTimeImmutable $cutoff): int
    {
        $sql = 'DELETE FROM alerts WHERE resolved_at IS NOT NULL AND resolved_at < :cutoff';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s')]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to purge resolved alerts.', previous: $exception);
        }

        return $stmt->rowCount();
    }
}
