<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Weather;

use DateTimeImmutable;
use JsonSerializable;

class WeatherReading implements JsonSerializable
{
    private function __construct(
        private readonly float             $temperatureCelsius,
        private readonly float             $apparentTemperatureCelsius,
        private readonly int               $conditionCode,
        private readonly int               $humidityPercent,
        private readonly float             $precipitationMm,
        private readonly int               $cloudCoverPercent,
        private readonly float             $pressureMslHpa,
        private readonly float             $windSpeedKmh,
        private readonly int               $windDirectionDegrees,
        private readonly float             $windGustsKmh,
        private readonly ?float            $uvIndex,
        private readonly bool              $isDay,
        private readonly DateTimeImmutable $fetchedAt,
    ) {
    }

    public static function create(
        float             $temperatureCelsius,
        float             $apparentTemperatureCelsius,
        int               $conditionCode,
        int               $humidityPercent,
        float             $precipitationMm,
        int               $cloudCoverPercent,
        float             $pressureMslHpa,
        float             $windSpeedKmh,
        int               $windDirectionDegrees,
        float             $windGustsKmh,
        ?float            $uvIndex,
        bool              $isDay,
        DateTimeImmutable $fetchedAt,
    ): self {
        return new self(
            $temperatureCelsius,
            $apparentTemperatureCelsius,
            $conditionCode,
            $humidityPercent,
            $precipitationMm,
            $cloudCoverPercent,
            $pressureMslHpa,
            $windSpeedKmh,
            $windDirectionDegrees,
            $windGustsKmh,
            $uvIndex,
            $isDay,
            $fetchedAt,
        );
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            (float) $row['temperature_celsius'],
            (float) $row['apparent_temperature_celsius'],
            (int) $row['condition_code'],
            (int) $row['humidity_percent'],
            (float) $row['precipitation_mm'],
            (int) $row['cloud_cover_percent'],
            (float) $row['pressure_msl_hpa'],
            (float) $row['wind_speed_kmh'],
            (int) $row['wind_direction_degrees'],
            (float) $row['wind_gusts_kmh'],
            $row['uv_index'] !== null ? (float) $row['uv_index'] : null,
            (bool) $row['is_day'],
            new DateTimeImmutable($row['fetched_at']),
        );
    }

    public function getTemperatureCelsius(): float
    {
        return $this->temperatureCelsius;
    }

    public function getApparentTemperatureCelsius(): float
    {
        return $this->apparentTemperatureCelsius;
    }

    public function getConditionCode(): int
    {
        return $this->conditionCode;
    }

    public function getHumidityPercent(): int
    {
        return $this->humidityPercent;
    }

    public function getPrecipitationMm(): float
    {
        return $this->precipitationMm;
    }

    public function getCloudCoverPercent(): int
    {
        return $this->cloudCoverPercent;
    }

    public function getPressureMslHpa(): float
    {
        return $this->pressureMslHpa;
    }

    public function getWindSpeedKmh(): float
    {
        return $this->windSpeedKmh;
    }

    public function getWindDirectionDegrees(): int
    {
        return $this->windDirectionDegrees;
    }

    public function getWindGustsKmh(): float
    {
        return $this->windGustsKmh;
    }

    public function getUvIndex(): ?float
    {
        return $this->uvIndex;
    }

    public function isDay(): bool
    {
        return $this->isDay;
    }

    public function getFetchedAt(): DateTimeImmutable
    {
        return $this->fetchedAt;
    }

    public function jsonSerialize(): array
    {
        $condition = WeatherCondition::fromWmoCode($this->conditionCode, $this->isDay);

        return [
            'temperatureCelsius' => $this->temperatureCelsius,
            'apparentTemperatureCelsius' => $this->apparentTemperatureCelsius,
            'condition' => $condition->getLabel(),
            'icon' => $condition->getIcon(),
            'humidityPercent' => $this->humidityPercent,
            'precipitationMm' => $this->precipitationMm,
            'cloudCoverPercent' => $this->cloudCoverPercent,
            'pressureMslHpa' => $this->pressureMslHpa,
            'windSpeedKmh' => $this->windSpeedKmh,
            'windDirectionDegrees' => $this->windDirectionDegrees,
            'windGustsKmh' => $this->windGustsKmh,
            'uvIndex' => $this->uvIndex,
            'isDay' => $this->isDay,
            'fetchedAt' => $this->fetchedAt->format('Y-m-d H:i:s'),
        ];
    }
}
