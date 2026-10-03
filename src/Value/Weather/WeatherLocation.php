<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Weather;

use JsonSerializable;

class WeatherLocation implements JsonSerializable
{
    private function __construct(
        private readonly string $city,
        private readonly float  $latitude,
        private readonly float  $longitude,
        private readonly string $timezone,
    ) {
    }

    public static function create(string $city, float $latitude, float $longitude, string $timezone): self
    {
        return new self($city, $latitude, $longitude, $timezone);
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            $row['city'],
            (float) $row['latitude'],
            (float) $row['longitude'],
            $row['timezone'],
        );
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function jsonSerialize(): array
    {
        return [
            'city' => $this->city,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timezone' => $this->timezone,
        ];
    }
}
