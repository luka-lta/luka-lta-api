<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Weather;

use JsonSerializable;

class WeatherForecastDay implements JsonSerializable
{
    private function __construct(
        private readonly string $date,
        private readonly float  $temperatureMinCelsius,
        private readonly float  $temperatureMaxCelsius,
        private readonly int    $conditionCode,
        private readonly float  $precipitationSumMm,
        private readonly int    $precipitationProbabilityPercent,
        private readonly ?float $uvIndexMax,
        private readonly string $sunrise,
        private readonly string $sunset,
        private readonly ?float $windSpeedMaxKmh,
        private readonly ?float $windGustsMaxKmh,
        private readonly ?int   $windDirectionDominantDegrees,
    ) {
    }

    public static function create(
        string $date,
        float  $temperatureMinCelsius,
        float  $temperatureMaxCelsius,
        int    $conditionCode,
        float  $precipitationSumMm,
        int    $precipitationProbabilityPercent,
        ?float $uvIndexMax,
        string $sunrise,
        string $sunset,
        ?float $windSpeedMaxKmh,
        ?float $windGustsMaxKmh,
        ?int   $windDirectionDominantDegrees,
    ): self {
        return new self(
            $date,
            $temperatureMinCelsius,
            $temperatureMaxCelsius,
            $conditionCode,
            $precipitationSumMm,
            $precipitationProbabilityPercent,
            $uvIndexMax,
            $sunrise,
            $sunset,
            $windSpeedMaxKmh,
            $windGustsMaxKmh,
            $windDirectionDominantDegrees,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['date'],
            (float) $data['temperatureMinCelsius'],
            (float) $data['temperatureMaxCelsius'],
            (int) $data['conditionCode'],
            (float) $data['precipitationSumMm'],
            (int) $data['precipitationProbabilityPercent'],
            $data['uvIndexMax'] !== null ? (float) $data['uvIndexMax'] : null,
            (string) $data['sunrise'],
            (string) $data['sunset'],
            isset($data['windSpeedMaxKmh']) ? (float) $data['windSpeedMaxKmh'] : null,
            isset($data['windGustsMaxKmh']) ? (float) $data['windGustsMaxKmh'] : null,
            isset($data['windDirectionDominantDegrees']) ? (int) $data['windDirectionDominantDegrees'] : null,
        );
    }

    public function jsonSerialize(): array
    {
        $condition = WeatherCondition::fromWmoCode($this->conditionCode, true);

        return [
            'date' => $this->date,
            'temperatureMinCelsius' => $this->temperatureMinCelsius,
            'temperatureMaxCelsius' => $this->temperatureMaxCelsius,
            'condition' => $condition->getLabel(),
            'icon' => $condition->getIcon(),
            'precipitationSumMm' => $this->precipitationSumMm,
            'precipitationProbabilityPercent' => $this->precipitationProbabilityPercent,
            'uvIndexMax' => $this->uvIndexMax,
            'sunrise' => $this->sunrise,
            'sunset' => $this->sunset,
            'windSpeedMaxKmh' => $this->windSpeedMaxKmh,
            'windGustsMaxKmh' => $this->windGustsMaxKmh,
            'windDirectionDominantDegrees' => $this->windDirectionDominantDegrees,
        ];
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'temperatureMinCelsius' => $this->temperatureMinCelsius,
            'temperatureMaxCelsius' => $this->temperatureMaxCelsius,
            'conditionCode' => $this->conditionCode,
            'precipitationSumMm' => $this->precipitationSumMm,
            'precipitationProbabilityPercent' => $this->precipitationProbabilityPercent,
            'uvIndexMax' => $this->uvIndexMax,
            'sunrise' => $this->sunrise,
            'sunset' => $this->sunset,
            'windSpeedMaxKmh' => $this->windSpeedMaxKmh,
            'windGustsMaxKmh' => $this->windGustsMaxKmh,
            'windDirectionDominantDegrees' => $this->windDirectionDominantDegrees,
        ];
    }
}
