<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Calendar\CalendarSource;
use PDO;
use PDOException;

class CalendarSourceRepository
{
    private const string SOURCE_SELECT = 'SELECT id, type, name, url, color, is_enabled FROM calendar_sources';

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /** @return CalendarSource[] */
    public function loadEnabled(): array
    {
        $sql = self::SOURCE_SELECT . ' WHERE is_enabled = 1 ORDER BY name ASC';

        try {
            $stmt = $this->pdo->query($sql);
            $sources = [];
            foreach ($stmt as $row) {
                $sources[] = CalendarSource::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch calendar sources.', previous: $exception);
        }

        return $sources;
    }

    /** @return CalendarSource[] */
    public function loadAll(): array
    {
        $sql = self::SOURCE_SELECT . ' ORDER BY name ASC';

        try {
            $stmt = $this->pdo->query($sql);
            $sources = [];
            foreach ($stmt as $row) {
                $sources[] = CalendarSource::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch calendar sources.', previous: $exception);
        }

        return $sources;
    }

    public function create(CalendarSource $source): CalendarSource
    {
        $sql = <<<SQL
            INSERT INTO calendar_sources (type, name, url, color, is_enabled)
            VALUES (:type, :name, :url, :color, :is_enabled)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'type' => $source->getType(),
                'name' => $source->getName(),
                'url' => $source->getUrl(),
                'color' => $source->getColor(),
                'is_enabled' => (int) $source->isEnabled(),
            ]);
            $id = (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to create calendar source.', previous: $exception);
        }

        $sql = self::SOURCE_SELECT . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return CalendarSource::fromDatabase($stmt->fetch());
    }

    public function update(int $id, array $data): CalendarSource
    {
        $fields = [];
        $params = ['id' => $id];

        if (array_key_exists('name', $data)) {
            $fields[] = 'name = :name';
            $params['name'] = (string) $data['name'];
        }
        if (array_key_exists('url', $data)) {
            $fields[] = 'url = :url';
            $params['url'] = (string) $data['url'];
        }
        if (array_key_exists('color', $data)) {
            $fields[] = 'color = :color';
            $params['color'] = $data['color'];
        }
        if (array_key_exists('isEnabled', $data)) {
            $fields[] = 'is_enabled = :is_enabled';
            $params['is_enabled'] = (int) $data['isEnabled'];
        }

        if (!empty($fields)) {
            $sql = 'UPDATE calendar_sources SET ' . implode(', ', $fields) . ' WHERE id = :id';

            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            } catch (PDOException $exception) {
                throw new ApiDatabaseException('Failed to update calendar source.', previous: $exception);
            }
        }

        $sql = self::SOURCE_SELECT . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            throw new ApiDatabaseException("Calendar source {$id} not found.");
        }

        return CalendarSource::fromDatabase($row);
    }

    public function delete(int $id): void
    {
        $sql = 'DELETE FROM calendar_sources WHERE id = :id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to delete calendar source.', previous: $exception);
        }
    }
}
