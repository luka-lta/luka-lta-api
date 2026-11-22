<?php

declare(strict_types=1);

namespace LukaLtaApi\Weather;

use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use LukaLtaApi\Exception\ApiHttpException;
use LukaLtaApi\Value\Weather\WeatherAirQuality;
use LukaLtaApi\Value\Weather\WeatherForecastDay;
use LukaLtaApi\Value\Weather\WeatherHourPoint;
use LukaLtaApi\Value\Weather\WeatherReading;
use LukaLtaApi\Value\Weather\WeatherSnapshot;
use Throwable;

/**
 * Free, key-less weather provider (https://open-meteo.com). No account, no
 * quota config needed — swap for a different WeatherProviderInterface
 * implementation if that ever changes.
 */
class OpenMeteoWeatherProvider implements WeatherProviderInterface
{
    private const string CURRENT_FIELDS = 'temperature_2m,apparent_temperature,relative_humidity_2m,'
        . 'weather_code,precipitation,cloud_cover,pressure_msl,wind_speed_10m,wind_direction_10m,'
        . 'wind_gusts_10m,is_day,uv_index';

    private const string HOURLY_FIELDS = 'temperature_2m,precipitation_probability,precipitation,weather_code,is_day,'
        . 'relative_humidity_2m,dew_point_2m,wind_speed_10m,wind_direction_10m,wind_gusts_10m,visibility,'
        . 'cloud_cover,uv_index';

    private const string DAILY_FIELDS = 'weather_code,temperature_2m_max,temperature_2m_min,'
        . 'sunrise,sunset,uv_index_max,precipitation_sum,precipitation_probability_max,'
        . 'wind_speed_10m_max,wind_gusts_10m_max,wind_direction_10m_dominant';

    private const string AIR_QUALITY_FIELDS = 'european_aqi,pm2_5,pm10';

    /** How many hours of hourly forecast to request/keep — a day-ish of lookahead. */
    private const int FORECAST_HOURS = 24;

    /** Request timeout in seconds for both Open-Meteo APIs. */
    private const int TIMEOUT_SECONDS = 10;

    private readonly Client $client;

    private readonly Client $airQualityClient;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.open-meteo.com',
            'timeout' => self::TIMEOUT_SECONDS,
        ]);
        $this->airQualityClient = new Client([
            'base_uri' => 'https://air-quality-api.open-meteo.com',
            'timeout' => self::TIMEOUT_SECONDS,
        ]);
    }

    public function fetch(float $latitude, float $longitude, string $timezone): WeatherSnapshot
    {
        try {
            $response = $this->client->get('/v1/forecast', [
                'query' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => self::CURRENT_FIELDS,
                    'hourly' => self::HOURLY_FIELDS,
                    'daily' => self::DAILY_FIELDS,
                    'forecast_days' => 7,
                    'timezone' => $timezone,
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new ApiHttpException('Failed to reach Open-Meteo.', previous: $exception);
        }

        try {
            $payload = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new ApiHttpException('Failed to decode Open-Meteo response.', previous: $exception);
        }

        return new WeatherSnapshot(
            $this->toCurrentReading($payload['current'] ?? []),
            $this->toHourlyPoints($payload['hourly'] ?? []),
            $this->toForecastDays($payload['daily'] ?? []),
            $this->fetchAirQuality($latitude, $longitude, $timezone),
        );
    }

    /**
     * Air quality is a separate Open-Meteo API and purely supplementary — when it
     * fails, the forecast is still stored and the UI hides the air quality block.
     */
    private function fetchAirQuality(float $latitude, float $longitude, string $timezone): ?WeatherAirQuality
    {
        try {
            $response = $this->airQualityClient->get('/v1/air-quality', [
                'query' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => self::AIR_QUALITY_FIELDS,
                    'timezone' => $timezone,
                ],
            ]);
            $payload = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);
        } catch (GuzzleException | JsonException) {
            return null;
        }

        $current = $payload['current'] ?? [];
        $airQuality = WeatherAirQuality::create(
            isset($current['european_aqi']) ? (int) $current['european_aqi'] : null,
            isset($current['pm2_5']) ? (float) $current['pm2_5'] : null,
            isset($current['pm10']) ? (float) $current['pm10'] : null,
            (string) ($current['time'] ?? ''),
        );

        return $airQuality->hasValues() ? $airQuality : null;
    }

    private function toCurrentReading(array $current): WeatherReading
    {
        return WeatherReading::create(
            (float) ($current['temperature_2m'] ?? 0.0),
            (float) ($current['apparent_temperature'] ?? 0.0),
            (int) ($current['weather_code'] ?? -1),
            (int) ($current['relative_humidity_2m'] ?? 0),
            (float) ($current['precipitation'] ?? 0.0),
            (int) ($current['cloud_cover'] ?? 0),
            (float) ($current['pressure_msl'] ?? 0.0),
            (float) ($current['wind_speed_10m'] ?? 0.0),
            (int) ($current['wind_direction_10m'] ?? 0),
            (float) ($current['wind_gusts_10m'] ?? 0.0),
            isset($current['uv_index']) ? (float) $current['uv_index'] : null,
            ($current['is_day'] ?? 1) === 1,
            new DateTimeImmutable(),
        );
    }

    /** @return WeatherHourPoint[] */
    private function toHourlyPoints(array $hourly): array
    {
        $now = new DateTimeImmutable();
        $times = $hourly['time'] ?? [];
        $points = [];

        foreach ($times as $index => $time) {
            if (new DateTimeImmutable($time) < $now->modify('-1 hour')) {
                continue;
            }

            $points[] = WeatherHourPoint::create(
                $time,
                (float) ($hourly['temperature_2m'][$index] ?? 0.0),
                (int) ($hourly['precipitation_probability'][$index] ?? 0),
                (float) ($hourly['precipitation'][$index] ?? 0.0),
                (int) ($hourly['weather_code'][$index] ?? -1),
                ($hourly['is_day'][$index] ?? 1) === 1,
                $this->intOrNull($hourly, 'relative_humidity_2m', $index),
                $this->floatOrNull($hourly, 'dew_point_2m', $index),
                $this->floatOrNull($hourly, 'wind_speed_10m', $index),
                $this->intOrNull($hourly, 'wind_direction_10m', $index),
                $this->floatOrNull($hourly, 'wind_gusts_10m', $index),
                $this->floatOrNull($hourly, 'visibility', $index),
                $this->intOrNull($hourly, 'cloud_cover', $index),
                $this->floatOrNull($hourly, 'uv_index', $index),
            );

            if (count($points) >= self::FORECAST_HOURS) {
                break;
            }
        }

        return $points;
    }

    /** @return WeatherForecastDay[] */
    private function toForecastDays(array $daily): array
    {
        $dates = $daily['time'] ?? [];

        return array_map(
            fn (int $index, string $date) => WeatherForecastDay::create(
                $date,
                (float) ($daily['temperature_2m_min'][$index] ?? 0.0),
                (float) ($daily['temperature_2m_max'][$index] ?? 0.0),
                (int) ($daily['weather_code'][$index] ?? -1),
                (float) ($daily['precipitation_sum'][$index] ?? 0.0),
                (int) ($daily['precipitation_probability_max'][$index] ?? 0),
                isset($daily['uv_index_max'][$index]) ? (float) $daily['uv_index_max'][$index] : null,
                (string) ($daily['sunrise'][$index] ?? ''),
                (string) ($daily['sunset'][$index] ?? ''),
                $this->floatOrNull($daily, 'wind_speed_10m_max', $index),
                $this->floatOrNull($daily, 'wind_gusts_10m_max', $index),
                $this->intOrNull($daily, 'wind_direction_10m_dominant', $index),
            ),
            array_keys($dates),
            $dates,
        );
    }

    private function floatOrNull(array $series, string $field, int $index): ?float
    {
        return isset($series[$field][$index]) ? (float) $series[$field][$index] : null;
    }

    private function intOrNull(array $series, string $field, int $index): ?int
    {
        return isset($series[$field][$index]) ? (int) round((float) $series[$field][$index]) : null;
    }
}
