<?php

namespace LukaLtaApi\Queue;

use ClickHouseDB\Client;
use ClickHouseDB\Exception\DatabaseException;
use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Repository\GeoIpRepository;
use LukaLtaApi\Service\ChannelDetectorService;
use LukaLtaApi\Value\Device;
use LukaLtaApi\Value\GeoLocation;
use LukaLtaApi\Value\UserAgent;
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

        $batches = TrackingBatch::from(...$this->queue);

        $ips = [];

        /** @var PageViewEvent $batch */
        foreach ($batches as $batch) {
            $ips[] = $batch->getIpAddress();
        }

        // TODO: Fetch geolocation
/*        $geoData = $this->geoIpRepo->getCountryCodeOfIp(...$ips);*/
        $geoData = GeoLocation::from(null, null, null, null, null, null, null);
        $processedPageViews = [];

        /** @var PageViewEvent $batch */
        foreach ($batches as $batch) {
            $processedPageViews[] = PageViewData::from(
                $batch->getSiteId(),
                new DateTimeImmutable(),
                $batch->getPageInfo(),
                UserAgent::fromUserAgent($batch->getUserAgent()),
                $geoData,
                $batch->getScreenDimensions(),
                UrlParameter::fromRawString($batch->getPageInfo()->getQueryString()),
                Device::fromScreenDimension($batch->getScreenDimensions()),
                $batch->getProperties(),
                $batch->getPerformanceMetrics(),
                '2222', // TODO
                '23222', // TODO
                $batch->getReferrer(),
                $this->channelDetector->detectChannel(
                    $batch->getReferrer(),
                    $batch->getPageInfo()->getQueryString(),
                    $batch->getPageInfo()->getHostname()
                ),
                $batch->getLanguage(),
                $batch->getEventName(),
                $geoData->getIpAddress(),
                $batch->getEventType(),
            );
        }


        var_dump($processedPageViews);

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