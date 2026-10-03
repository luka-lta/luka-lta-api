<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use DateTimeImmutable;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Weather\WeatherForecastDay;
use LukaLtaApi\Value\Weather\WeatherHourPoint;
use LukaLtaApi\Value\Weather\WeatherReading;
use PDO;
use PDOException;

class WeatherRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function loadLatest(): ?WeatherReading
    {
        $sql = <<<SQL
            SELECT
                temperature_celsius, apparent_temperature_celsius, condition_code,
                humidity_percent, precipitation_mm, cloud_cover_percent, pressure_msl_hpa,
                wind_speed_kmh, wind_direction_degrees, wind_gusts_kmh, uv_index, is_day, fetched_at
            FROM weather_readings
            ORDER BY fetched_at DESC
            LIMIT 1
        SQL;

        try {
            $row = $this->pdo->query($sql)->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch latest weather reading.', previous: $exception);
        }

        return $row === false ? null : WeatherReading::fromDatabase($row);
    }

    public function purgeReadingsOlderThan(DateTimeImmutable $cutoff): int
    {
        $sql = 'DELETE FROM weather_readings WHERE fetched_at < :cutoff';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s')]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to purge weather readings.', previous: $exception);
        }

        return $stmt->rowCount();
    }

    public function insertReading(WeatherReading $reading): void
    {
        $sql = <<<SQL
            INSERT INTO weather_readings (
                temperature_celsius, apparent_temperature_celsius, condition_code, humidity_percent,
                precipitation_mm, cloud_cover_percent, pressure_msl_hpa, wind_speed_kmh,
                wind_direction_degrees, wind_gusts_kmh, uv_index, is_day, fetched_at
            ) VALUES (
                :temperature_celsius, :apparent_temperature_celsius, :condition_code, :humidity_percent,
                :precipitation_mm, :cloud_cover_percent, :pressure_msl_hpa, :wind_speed_kmh,
                :wind_direction_degrees, :wind_gusts_kmh, :uv_index, :is_day, :fetched_at
            )
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'temperature_celsius' => $reading->getTemperatureCelsius(),
                'apparent_temperature_celsius' => $reading->getApparentTemperatureCelsius(),
                'condition_code' => $reading->getConditionCode(),
                'humidity_percent' => $reading->getHumidityPercent(),
                'precipitation_mm' => $reading->getPrecipitationMm(),
                'cloud_cover_percent' => $reading->getCloudCoverPercent(),
                'pressure_msl_hpa' => $reading->getPressureMslHpa(),
                'wind_speed_kmh' => $reading->getWindSpeedKmh(),
                'wind_direction_degrees' => $reading->getWindDirectionDegrees(),
                'wind_gusts_kmh' => $reading->getWindGustsKmh(),
                'uv_index' => $reading->getUvIndex(),
                'is_day' => (int) $reading->isDay(),
                'fetched_at' => $reading->getFetchedAt()->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to store weather reading.', previous: $exception);
        }
    }

    /**
     * @return array{hourly: WeatherHourPoint[], daily: WeatherForecastDay[], fetchedAt: DateTimeImmutable}|null
     */
    public function loadForecastCache(): ?array
    {
        $sql = 'SELECT hourly_payload, daily_payload, fetched_at FROM weather_forecast_cache WHERE id = 1';

        try {
            $row = $this->pdo->query($sql)->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch weather forecast cache.', previous: $exception);
        }

        if ($row === false) {
            return null;
        }

        $hourly = json_decode($row['hourly_payload'], true, flags: JSON_THROW_ON_ERROR);
        $daily = json_decode($row['daily_payload'], true, flags: JSON_THROW_ON_ERROR);

        return [
            'hourly' => array_map(static fn (array $point) => WeatherHourPoint::fromArray($point), $hourly),
            'daily' => array_map(static fn (array $day) => WeatherForecastDay::fromArray($day), $daily),
            'fetchedAt' => new DateTimeImmutable($row['fetched_at']),
        ];
    }

    /**
     * @param WeatherHourPoint[]   $hourly
     * @param WeatherForecastDay[] $daily
     */
    public function saveForecastCache(array $hourly, array $daily, DateTimeImmutable $fetchedAt): void
    {
        $sql = <<<SQL
            INSERT INTO weather_forecast_cache (id, hourly_payload, daily_payload, fetched_at)
            VALUES (1, :hourly_payload, :daily_payload, :fetched_at)
            ON DUPLICATE KEY UPDATE
                hourly_payload = VALUES(hourly_payload),
                daily_payload  = VALUES(daily_payload),
                fetched_at     = VALUES(fetched_at)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'hourly_payload' => json_encode(
                    array_map(static fn (WeatherHourPoint $p) => $p->toArray(), $hourly),
                    JSON_THROW_ON_ERROR,
                ),
                'daily_payload' => json_encode(
                    array_map(static fn (WeatherForecastDay $d) => $d->toArray(), $daily),
                    JSON_THROW_ON_ERROR,
                ),
                'fetched_at' => $fetchedAt->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to store weather forecast cache.', previous: $exception);
        }
    }
}
