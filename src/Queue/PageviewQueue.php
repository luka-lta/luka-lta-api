<?php

namespace LukaLtaApi\Queue;

use ClickHouseDB\Client;
use ClickHouseDB\Exception\DatabaseException;
use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Repository\GeoIpRepository;
use LukaLtaApi\Service\ChannelDetectorService;
use LukaLtaApi\Value\GeoLocation;
use LukaLtaApi\Value\WebTracking\Tracking\Events\PageviewPayload;
use LukaLtaApi\Value\WebTracking\Tracking\PageInfo;
use LukaLtaApi\Value\WebTracking\Tracking\PageViewData;
use LukaLtaApi\Value\WebTracking\Tracking\PageViewEvent;
use LukaLtaApi\Value\WebTracking\Tracking\TrackingBatch;
use LukaLtaApi\Value\WebTracking\Tracking\UrlParameter;

class PageviewQueue
{
    private array $queue = [];
    private int $interval = 10; // seconds
    private bool $processing = false;

    public function __construct(
        private readonly Client $clickHouse,
        private readonly ChannelDetectorService $channelDetector,
        private readonly GeoIpRepository $geoIpRepo,
    ) {
    }

    public function add(PageViewEvent $pageview): void
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

        /** @var PageViewEvent $batch */
        foreach ($batches as $batch) {
            $ips[] = $batch->getIpAddress();
        }

        // TODO: Fetch geolocation
        $geoData = $this->geoIpRepo->getCountryCodeOfIp(...$ips);

        $processedPageViews = [];

        /** @var PageViewEvent $batch */
        foreach ($batches as $batch) {
            // TODO: Return trackingData as Object (https://github.com/rybbit-io/rybbit/blob/master/server/src/services/tracker/pageviewQueue.ts)
            $processedPageViews[] = PageViewData::from(
                $batch->getSiteId(),
                new DateTimeImmutable(),
                pageInfo: PageInfo::from(
                    $batch->getHostname(),
                    $batch->getPathname(),
                    $batch->getQueryString(),
                    $batch->getPageTitle(),
                ),
                language: $batch->getLanguage(),
                screenDimensions: $batch->getScreenDimensions(),
                channel: $this->channelDetector->detectChannel($batch->getReferrer(), $batch->getQueryString(), $batch->getHostname()),
                urlParameters: UrlParameter::fromRawString($batch->getQueryString()),
                geoLocation: $geoData,
                eventType: $batch->getEventType(),
                urlParameters: UrlParameter::fromRawString($batch->getQueryString()),
            );
        }


        try {
            // TODO: Check if this right
            $this->clickHouse->insert(
                'events',
                $processedPageViews,
                ['event_type', 'site_id', 'occurred_on', 'session_id', 'user_id', 'page_info', 'referrer', 'channel', 'browser', 'browser_version', 'os', 'os_version', 'language', 'screen_dimensions', 'device_type', 'geo_location', 'event_name', 'props', 'url_parameters', 'performance_metrics', 'ip_address']
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