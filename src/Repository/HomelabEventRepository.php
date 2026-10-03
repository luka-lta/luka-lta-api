<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use DateTimeImmutable;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Homelab\Event;
use LukaLtaApi\Value\Homelab\Events;
use PDO;
use PDOException;

class HomelabEventRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function insert(Event $event): void
    {
        $sql = <<<SQL
            INSERT INTO homelab_events (
                type, severity, title, description, container_id, host_id, metadata, occurred_at
            ) VALUES (
                :type, :severity, :title, :description, :container_id, :host_id, :metadata, :occurred_at
            )
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'type'         => $event->getType(),
                'severity'     => $event->getSeverity(),
                'title'        => $event->getTitle(),
                'description'  => $event->getDescription(),
                'container_id' => $event->getContainerId(),
                'host_id'      => $event->getHostId(),
                'metadata'     => json_encode($event->getMetadata(), JSON_THROW_ON_ERROR),
                'occurred_at'  => $event->getOccurredAt()->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to store event.', previous: $exception);
        }
    }

    public function loadRecent(int $limit = 100): Events
    {
        $sql = <<<SQL
            SELECT event_id, type, severity, title, description, container_id, host_id, metadata, occurred_at
            FROM homelab_events
            ORDER BY occurred_at DESC
            LIMIT :limit
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $events = [];
            foreach ($stmt as $row) {
                $events[] = Event::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch events.', previous: $exception);
        }

        return Events::from(...$events);
    }

    public function purgeOlderThan(DateTimeImmutable $cutoff): int
    {
        $sql = 'DELETE FROM homelab_events WHERE occurred_at < :cutoff';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s')]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to purge events.', previous: $exception);
        }

        return $stmt->rowCount();
    }
}
