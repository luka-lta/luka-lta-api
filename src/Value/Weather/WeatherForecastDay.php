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
        ];
    }
}
