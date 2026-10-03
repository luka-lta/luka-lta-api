<?php

declare(strict_types=1);

namespace LukaLtaApi\Weather;

use LukaLtaApi\Value\Weather\WeatherSnapshot;

/**
 * One implementation per weather data source. The service and UI depend only
 * on this interface and on WeatherSnapshot — swapping Open-Meteo for another
 * provider later means a new class here, nothing else changes.
 */
interface WeatherProviderInterface
{
    public function fetch(float $latitude, float $longitude, string $timezone): WeatherSnapshot;
}
