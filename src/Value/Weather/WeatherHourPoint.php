<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Weather;

use JsonSerializable;

class WeatherHourPoint implements JsonSerializable
{
    private function __construct(
        private readonly string $time,
        private readonly float  $temperatureCelsius,
        private readonly int    $precipitationProbabilityPercent,
        private readonly float  $precipitationMm,
        private readonly int    $conditionCode,
        private readonly bool   $isDay,
    ) {
    }

    public static function create(
        string $time,
        float  $temperatureCelsius,
        int    $precipitationProbabilityPercent,
        float  $precipitationMm,
        int    $conditionCode,
        bool   $isDay,
    ): self {
        return new self(
            $time,
            $temperatureCelsius,
            $precipitationProbabilityPercent,
            $precipitationMm,
            $conditionCode,
            $isDay,
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
        ];
    }
}
