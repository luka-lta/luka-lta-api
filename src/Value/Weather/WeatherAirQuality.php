<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Weather;

use JsonSerializable;

/**
 * Current air quality at the weather location. Each value is null when the
 * provider has no measurement for it (e.g. no model coverage).
 */
class WeatherAirQuality implements JsonSerializable
{
    private function __construct(
        private readonly ?int   $europeanAqi,
        private readonly ?float $pm25,
        private readonly ?float $pm10,
        private readonly string $measuredAt,
    ) {
    }

    public static function create(?int $europeanAqi, ?float $pm25, ?float $pm10, string $measuredAt): self
    {
        return new self($europeanAqi, $pm25, $pm10, $measuredAt);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['europeanAqi']) ? (int) $data['europeanAqi'] : null,
            isset($data['pm25']) ? (float) $data['pm25'] : null,
            isset($data['pm10']) ? (float) $data['pm10'] : null,
            (string) $data['measuredAt'],
        );
    }

    public function hasValues(): bool
    {
        return $this->europeanAqi !== null || $this->pm25 !== null || $this->pm10 !== null;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        return [
            'europeanAqi' => $this->europeanAqi,
            'pm25' => $this->pm25,
            'pm10' => $this->pm10,
            'measuredAt' => $this->measuredAt,
        ];
    }
}
