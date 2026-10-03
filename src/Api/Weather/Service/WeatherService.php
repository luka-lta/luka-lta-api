<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Weather\Service;

use DateTimeImmutable;
use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Repository\WeatherLocationRepository;
use LukaLtaApi\Repository\WeatherRepository;
use LukaLtaApi\Service\AlertManager;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\Weather\WeatherLocation;
use LukaLtaApi\Weather\WeatherProviderInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class WeatherService
{
    private const int STALE_AFTER_MINUTES = 45;
    private const int READING_RETENTION_DAYS = 30;
    private const string FETCH_FAILURE_TYPE = 'weather.fetch_failed';
    private const int FETCH_FAILURE_THRESHOLD = 3;

    public function __construct(
        private readonly WeatherProviderInterface  $provider,
        private readonly WeatherLocationRepository $locationRepository,
        private readonly WeatherRepository         $weatherRepository,
        private readonly AlertManager              $alertManager,
        private readonly LoggerInterface           $logger,
    ) {
    }

    public function refresh(): void
    {
        $location = $this->locationRepository->load();
        if ($location === null) {
            return;
        }

        try {
            $snapshot = $this->provider->fetch(
                $location->getLatitude(),
                $location->getLongitude(),
                $location->getTimezone(),
            );
            $now = new DateTimeImmutable();

            $this->weatherRepository->insertReading($snapshot->getCurrent());
            $this->weatherRepository->saveForecastCache($snapshot->getHourly(), $snapshot->getDaily(), $now);
            $this->weatherRepository->purgeReadingsOlderThan(
                $now->modify('-' . self::READING_RETENTION_DAYS . ' days'),
            );

            $this->alertManager->evaluate(
                false,
                'weather',
                null,
                self::FETCH_FAILURE_TYPE,
                'warning',
                'Weather fetch failing',
                '',
                [],
                self::FETCH_FAILURE_THRESHOLD,
            );
        } catch (Throwable $exception) {
            $this->alertManager->evaluate(
                true,
                'weather',
                null,
                self::FETCH_FAILURE_TYPE,
                'warning',
                'Weather fetch failing',
                $exception->getMessage(),
                [],
                self::FETCH_FAILURE_THRESHOLD,
            );

            $this->logger->error('Weather fetch failed', ['message' => $exception->getMessage()]);
        }
    }

    public function getSummary(): ApiResult
    {
        $location = $this->locationRepository->load();
        if ($location === null) {
            return ApiResult::from(JsonResult::from('Weather not configured.', [
                'status' => 'not_configured',
                'location' => null,
                'current' => null,
            ]));
        }

        $latest = $this->weatherRepository->loadLatest();
        if ($latest === null) {
            return ApiResult::from(JsonResult::from('No weather data yet.', [
                'status' => 'pending',
                'location' => $location,
                'current' => null,
            ]));
        }

        return ApiResult::from(JsonResult::from('Weather fetched.', [
            'status' => $this->status($latest->getFetchedAt()),
            'location' => $location,
            'current' => $latest,
        ]));
    }

    public function getDetail(): ApiResult
    {
        $location = $this->locationRepository->load();
        if ($location === null) {
            return ApiResult::from(JsonResult::from('Weather not configured.', [
                'status' => 'not_configured',
                'location' => null,
                'current' => null,
                'hourly' => [],
                'daily' => [],
            ]));
        }

        $latest = $this->weatherRepository->loadLatest();
        $forecast = $this->weatherRepository->loadForecastCache();

        return ApiResult::from(JsonResult::from('Weather detail fetched.', [
            'status' => $latest !== null ? $this->status($latest->getFetchedAt()) : 'pending',
            'location' => $location,
            'current' => $latest,
            'hourly' => $forecast['hourly'] ?? [],
            'daily' => $forecast['daily'] ?? [],
        ]));
    }

    public function getLocation(): ApiResult
    {
        return ApiResult::from(JsonResult::from('Weather location fetched.', [
            'location' => $this->locationRepository->load(),
        ]));
    }

    public function updateLocation(array $data): ApiResult
    {
        if (!isset($data['city'], $data['latitude'], $data['longitude'])) {
            throw new ApiValidationException('city, latitude and longitude are required.', 400);
        }

        $location = WeatherLocation::create(
            (string) $data['city'],
            (float) $data['latitude'],
            (float) $data['longitude'],
            (string) ($data['timezone'] ?? 'auto'),
        );
        $this->locationRepository->update($location);

        return ApiResult::from(JsonResult::from('Weather location updated.', ['location' => $location]));
    }

    private function status(DateTimeImmutable $fetchedAt): string
    {
        $ageMinutes = ((new DateTimeImmutable())->getTimestamp() - $fetchedAt->getTimestamp()) / 60;

        return $ageMinutes > self::STALE_AFTER_MINUTES ? 'stale' : 'ok';
    }
}
