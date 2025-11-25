<?php

namespace LukaLtaApi\Queue;

use ClickHouseDB\Client;
use ClickHouseDB\Exception\DatabaseException;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\WebTracking\Tracking\AbstractTrackingPayload;
use LukaLtaApi\Value\WebTracking\Tracking\TrackingBatch;

class PageviewQueue
{
    private array $queue = [];
    private int $interval = 10; // seconds
    private bool $processing = false;

    public function __construct(
        private readonly Client $clickHouse,
    ) {
    }

    public function add(array $pageview): void
    {
        $this->queue[] = $pageview;

        // Auto-process if queue is full
        if (count($this->queue) === 1) {
            $this->processQueue();
        } else {
            // Save queue to file for persistence
            $this->saveQueue();
        }
    }

    public function processQueue(): void
    {
        if ($this->processing || empty($this->queue)) {
            return;
        }

        $this->processing = true;

        $batches = TrackingBatch::from($this->queue);

        $ips = [];

        /** @var AbstractTrackingPayload $batch */
        foreach ($batches as $batch) {
            $ips[] = $batch->getIpAddress();
        }

        // TODO: Fetch geolocation
        $geoData = fetchGeoLocation($ips);

        $processedPageViews = [];

        /** @var AbstractTrackingPayload $batch */
        foreach ($batches as $batch) {
            $countryCode = $geoData?->countryIso || "";
            $regionCode = $geoData?->region || "";
            $latitude = $geoData?->latitude || 0;
            $longitude = $geoData?->longitude || 0;
            $city = $geoData?->city || "";
            $timezone = $geoData?->timeZone || "";

            // TODO: Return trackingData as Object (https://github.com/rybbit-io/rybbit/blob/master/server/src/services/tracker/pageviewQueue.ts)
            $processedPageViews[] = [];
        }


        try {
            // TODO: Check if this right
            $this->clickHouse->insert(
                'events',
                $processedPageViews,
            );
        } catch (DatabaseException $exception) {
            throw new ApiDatabaseException(
                'Failed to insert pageView to ClickHouse',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }
}