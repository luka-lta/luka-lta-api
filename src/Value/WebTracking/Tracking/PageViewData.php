<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\WebTracking\Tracking;

use DateTimeImmutable;
use LukaLtaApi\Value\GeoLocation;
use LukaLtaApi\Value\PerformanceMetrics;

class PageViewData
{
    public function __construct(
        private readonly string $siteId,
        private readonly DateTimeImmutable $occurredOn,
        private readonly string $sessionId,
        private readonly string $userId,
        private readonly PageInfo $pageInfo,
        private readonly ?string $referrer,
        private readonly ?string $channel,
        private readonly ?string $browser,
        private readonly ?string $browserVersion,
        private readonly ?string $os,
        private readonly ?string $osVersion,
        private readonly ?string $language,
        private readonly ?ScreenDimensions $screenDimensions,
        private readonly ?string $deviceType,
        private readonly GeoLocation $geoLocation,
        private readonly EventType $eventType = EventType::PAGEVIEW,
        private readonly ?string $eventName,
        private readonly ?string $props, // TODO: Change to array or object later
        private readonly UrlParameter $urlParameters,
        private readonly ?PerformanceMetrics $performanceMetrics,
        private readonly ?string $ipAddress,
    ) {
    }

    public static function from(
        string $siteId,
        DateTimeImmutable $occurredOn,
        string $sessionId,
        string $userId,
        PageInfo $pageInfo,
        ?string $referrer,
        ?string $channel,
        ?string $browser,
        ?string $browserVersion,
        ?string $os,
        ?string $osVersion,
        ?string $language,
        ?ScreenDimensions $screenDimensions,
        ?string $deviceType,
        GeoLocation $geoLocation,
        EventType $eventType = EventType::PAGEVIEW,
        ?string $eventName,
        ?string $props,
        UrlParameter $urlParameters,
        ?PerformanceMetrics $performanceMetrics,
        ?string $ipAddress,
    ): self {
        return new self(
            $siteId,
            $occurredOn,
            $sessionId,
            $userId,
            $pageInfo,
            $referrer,
            $channel,
            $browser,
            $browserVersion,
            $os,
            $osVersion,
            $language,
            $screenDimensions,
            $deviceType,
            $geoLocation,
            $eventType,
            $eventName,
            $props,
            $urlParameters,
            $performanceMetrics,
            $ipAddress,
        );
    }

    public function toArray(): array
    {
        return [

        ];
    }
}
