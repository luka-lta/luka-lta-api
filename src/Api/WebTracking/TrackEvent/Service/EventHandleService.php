<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\WebTracking\TrackEvent\Service;

use DateTimeImmutable;
use LukaLtaApi\Api\WebTracking\TrackEvent\Repository\TrackEventRepository;
use LukaLtaApi\Service\ChannelDetectorService;
use LukaLtaApi\Value\Device;
use LukaLtaApi\Value\GeoLocation;
use LukaLtaApi\Value\UserAgent;
use LukaLtaApi\Value\WebTracking\Tracking\PageViewData;
use LukaLtaApi\Value\WebTracking\Tracking\PageViewEvent;
use LukaLtaApi\Value\WebTracking\Tracking\UrlParameter;

class EventHandleService
{
    public function __construct(
        private readonly ChannelDetectorService $channelDetector,
        private readonly TrackEventRepository $trackEventRepository,
    ) {
    }

    public function handleEvent(PageViewEvent $event): void
    {
        $geoData = GeoLocation::from(null, null, null, null, null, null, null);
        $pageInfo = $event->getPageInfo();
        $queryString = $pageInfo->getQueryString();

        // TODO: Add WebVital to Database

        $pageviewData = PageViewData::from(
            $event->getSiteId(),
            new DateTimeImmutable(),
            $pageInfo,
            UserAgent::fromUserAgent($event->getUserAgent()),
            $geoData,
            $event->getScreenDimensions(),
            UrlParameter::fromRawString($queryString),
            Device::fromScreenDimension($event->getScreenDimensions()),
            $event->getProperties(),
            $event->getPerformanceMetrics(),
            '23222', // TODO
            'ABCDE',
            $event->getReferrer(),
            $this->channelDetector->detectChannel(
                $event->getReferrer(),
                $queryString,
                $pageInfo->getHostname()
            ),
            $event->getLanguage(),
            $event->getEventName(),
            $event->getIpAddress(),
            $event->getEventType(),
        );

        $this->trackEventRepository->insertEvent($pageviewData);
    }
}
