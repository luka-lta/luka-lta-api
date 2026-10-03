<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Weather\WeatherLocation;
use PDO;
use PDOException;

class WeatherLocationRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function load(): ?WeatherLocation
    {
        $sql = 'SELECT city, latitude, longitude, timezone FROM weather_location WHERE id = 1';

        try {
            $row = $this->pdo->query($sql)->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch weather location.', previous: $exception);
        }

        return $row === false ? null : WeatherLocation::fromDatabase($row);
    }

    public function update(WeatherLocation $location): void
    {
        $sql = <<<SQL
            INSERT INTO weather_location (id, city, latitude, longitude, timezone)
            VALUES (1, :city, :latitude, :longitude, :timezone)
            ON DUPLICATE KEY UPDATE
                city      = VALUES(city),
                latitude  = VALUES(latitude),
                longitude = VALUES(longitude),
                timezone  = VALUES(timezone)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'city' => $location->getCity(),
                'latitude' => $location->getLatitude(),
                'longitude' => $location->getLongitude(),
                'timezone' => $location->getTimezone(),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to update weather location.', previous: $exception);
        }
    }
}
