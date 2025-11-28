<?php

declare(strict_types=1);

namespace LukaLtaApi\Value;

class GeoLocation
{
    private function __construct(
        private readonly ?string $countryCode,
        private readonly ?string $regionCode,
        private readonly ?float $latitude = 0,
        private readonly ?float $longitude = 0,
        private readonly ?string $city = null,
        private readonly ?string $timezone = null,
    ) {
    }

    public static function from(
        ?string $countryCode,
        ?string $regionCode,
        ?float $latitude,
        ?float $longitude,
        ?string $city,
        ?string $timezone,
    ): self {
        return new self(
            $countryCode,
            $regionCode,
            $latitude,
            $longitude,
            $city,
            $timezone,
        );
    }

    public static function fromGeoApi(array $result): self
    {
        return new self(
            isset($result['country_code']) ? $result['country_code'] : null,
            isset($result['region_code']) ? $result['region_code'] : null,
            isset($result['latitude']) ? (float)$result['latitude'] : null,
            isset($result['longitude']) ? (float)$result['longitude'] : null,
            isset($result['city']) ? $result['city'] : null,
            isset($result['timezone']['id']) ? $result['timezone']['id'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'countryCode' => $this?->countryCode,
            'regionCode' => $this?->regionCode,
            'latitude' => $this?->latitude,
            'longitude' => $this?->longitude,
            'city' => $this?->city,
            'timezone' => $this?->timezone,
            'region' => $this?->getRegion(),
        ];
    }

    public function getRegion(): string
    {
        return $this->countryCode . '-' . $this->regionCode;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getRegionCode(): string
    {
        return $this->regionCode;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }
}
