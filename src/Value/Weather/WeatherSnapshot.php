<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Weather;

/**
 * What a WeatherProviderInterface::fetch() call returns — the normalized shape
 * every provider (Open-Meteo today, anything else later) must produce. The
 * service and UI only ever see this, never a provider's raw response.
 */
class WeatherSnapshot
{
    /**
     * @param WeatherHourPoint[]   $hourly
     * @param WeatherForecastDay[] $daily
     */
    public function __construct(
        private readonly WeatherReading $current,
        private readonly array          $hourly,
        private readonly array          $daily,
    ) {
    }

    public function getCurrent(): WeatherReading
    {
        return $this->current;
    }

    /** @return WeatherHourPoint[] */
    public function getHourly(): array
    {
        return $this->hourly;
    }

    /** @return WeatherForecastDay[] */
    public function getDaily(): array
    {
        return $this->daily;
    }
}
