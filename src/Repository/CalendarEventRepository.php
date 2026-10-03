<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use DateTimeImmutable;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Calendar\CalendarEvent;
use PDO;
use PDOException;

class CalendarEventRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @return array<int, array{row: array, sourceName: string, sourceColor: ?string}>
     */
    public function loadBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $sql = <<<SQL
            SELECT e.*, s.name AS source_name, s.color AS source_color
            FROM calendar_events_cache e
            JOIN calendar_sources s ON s.id = e.source_id
            WHERE e.ends_at >= :from AND e.starts_at <= :to
            ORDER BY e.starts_at ASC
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'from' => $from->format('Y-m-d H:i:s'),
                'to' => $to->format('Y-m-d H:i:s'),
            ]);

            $events = [];
            foreach ($stmt as $row) {
                $events[] = CalendarEvent::fromDatabase($row, $row['source_name'], $row['source_color']);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch calendar events.', previous: $exception);
        }

        return $events;
    }

    /**
     * Replaces every cached event for this source with the freshly synced set —
     * an ICS feed gives no cheap "what changed" diff, and per-source volume is
     * small enough that delete-then-insert is simpler than reconciling rows.
     *
     * @param CalendarEvent[] $events
     */
    public function replaceForSource(int $sourceId, array $events, DateTimeImmutable $syncedAt): void
    {
        try {
            $this->pdo->beginTransaction();

            $delete = $this->pdo->prepare('DELETE FROM calendar_events_cache WHERE source_id = :source_id');
            $delete->execute(['source_id' => $sourceId]);

            $insert = $this->pdo->prepare(<<<SQL
                INSERT INTO calendar_events_cache (
                    source_id, external_uid, title, starts_at, ends_at, is_all_day,
                    location, description, attendees, url, synced_at
                ) VALUES (
                    :source_id, :external_uid, :title, :starts_at, :ends_at, :is_all_day,
                    :location, :description, :attendees, :url, :synced_at
                )
            SQL);

            foreach ($events as $event) {
                $insert->execute([
                    'source_id' => $sourceId,
                    'external_uid' => $event->getExternalUid(),
                    'title' => $event->getTitle(),
                    'starts_at' => $event->getStartsAt()->format('Y-m-d H:i:s'),
                    'ends_at' => $event->getEndsAt()->format('Y-m-d H:i:s'),
                    'is_all_day' => (int) $event->isAllDay(),
                    'location' => $event->getLocation(),
                    'description' => $event->getDescription(),
                    'attendees' => json_encode($event->getAttendees(), JSON_THROW_ON_ERROR),
                    'url' => $event->getUrl(),
                    'synced_at' => $syncedAt->format('Y-m-d H:i:s'),
                ]);
            }

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new ApiDatabaseException('Failed to store calendar events.', previous: $exception);
        }
    }
}
