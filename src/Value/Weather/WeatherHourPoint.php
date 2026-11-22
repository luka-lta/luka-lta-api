<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Weather;

use JsonSerializable;

/**
 * One hour of forecast. The optional measurements are null when the provider
 * did not deliver them — and for forecast caches written before they existed.
 */
class WeatherHourPoint implements JsonSerializable
{
    private function __construct(
        private readonly string $time,
        private readonly float  $temperatureCelsius,
        private readonly int    $precipitationProbabilityPercent,
        private readonly float  $precipitationMm,
        private readonly int    $conditionCode,
        private readonly bool   $isDay,
        private readonly ?int   $humidityPercent,
        private readonly ?float $dewPointCelsius,
        private readonly ?float $windSpeedKmh,
        private readonly ?int   $windDirectionDegrees,
        private readonly ?float $windGustsKmh,
        private readonly ?float $visibilityMeters,
        private readonly ?int   $cloudCoverPercent,
        private readonly ?float $uvIndex,
    ) {
    }

    public static function create(
        string $time,
        float  $temperatureCelsius,
        int    $precipitationProbabilityPercent,
        float  $precipitationMm,
        int    $conditionCode,
        bool   $isDay,
        ?int   $humidityPercent,
        ?float $dewPointCelsius,
        ?float $windSpeedKmh,
        ?int   $windDirectionDegrees,
        ?float $windGustsKmh,
        ?float $visibilityMeters,
        ?int   $cloudCoverPercent,
        ?float $uvIndex,
    ): self {
        return new self(
            $time,
            $temperatureCelsius,
            $precipitationProbabilityPercent,
            $precipitationMm,
            $conditionCode,
            $isDay,
            $humidityPercent,
            $dewPointCelsius,
            $windSpeedKmh,
            $windDirectionDegrees,
            $windGustsKmh,
            $visibilityMeters,
            $cloudCoverPercent,
            $uvIndex,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['time'],
            (float) $data['temperatureCelsius'],
            (int) $data['precipitationProbabilityPercent'],
            (float) ($data['precipitationMm'] ?? 0.0),
            (int) $data['conditionCode'],
            (bool) $data['isDay'],
            isset($data['humidityPercent']) ? (int) $data['humidityPercent'] : null,
            isset($data['dewPointCelsius']) ? (float) $data['dewPointCelsius'] : null,
            isset($data['windSpeedKmh']) ? (float) $data['windSpeedKmh'] : null,
            isset($data['windDirectionDegrees']) ? (int) $data['windDirectionDegrees'] : null,
            isset($data['windGustsKmh']) ? (float) $data['windGustsKmh'] : null,
            isset($data['visibilityMeters']) ? (float) $data['visibilityMeters'] : null,
            isset($data['cloudCoverPercent']) ? (int) $data['cloudCoverPercent'] : null,
            isset($data['uvIndex']) ? (float) $data['uvIndex'] : null,
        );
    }

    public function jsonSerialize(): array
    {
        $condition = WeatherCondition::fromWmoCode($this->conditionCode, $this->isDay);

        return [
            'time' => $this->time,
            'temperatureCelsius' => $this->temperatureCelsius,
            'precipitationProbabilityPercent' => $this->precipitationProbabilityPercent,
            'precipitationMm' => $this->precipitationMm,
            'condition' => $condition->getLabel(),
            'icon' => $condition->getIcon(),
            'isDay' => $this->isDay,
            'humidityPercent' => $this->humidityPercent,
            'dewPointCelsius' => $this->dewPointCelsius,
            'windSpeedKmh' => $this->windSpeedKmh,
            'windDirectionDegrees' => $this->windDirectionDegrees,
            'windGustsKmh' => $this->windGustsKmh,
            'visibilityMeters' => $this->visibilityMeters,
            'cloudCoverPercent' => $this->cloudCoverPercent,
            'uvIndex' => $this->uvIndex,
        ];
    }

    public function toArray(): array
    {
        return [
            'time' => $this->time,
            'temperatureCelsius' => $this->temperatureCelsius,
            'precipitationProbabilityPercent' => $this->precipitationProbabilityPercent,
            'precipitationMm' => $this->precipitationMm,
            'conditionCode' => $this->conditionCode,
            'isDay' => $this->isDay,
            'humidityPercent' => $this->humidityPercent,
            'dewPointCelsius' => $this->dewPointCelsius,
            'windSpeedKmh' => $this->windSpeedKmh,
            'windDirectionDegrees' => $this->windDirectionDegrees,
            'windGustsKmh' => $this->windGustsKmh,
            'visibilityMeters' => $this->visibilityMeters,
            'cloudCoverPercent' => $this->cloudCoverPercent,
            'uvIndex' => $this->uvIndex,
        ];
    }
}
