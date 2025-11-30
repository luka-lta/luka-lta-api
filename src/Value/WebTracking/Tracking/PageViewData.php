<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\WebTracking\Tracking;

use DateTimeImmutable;
use LukaLtaApi\Value\Device;
use LukaLtaApi\Value\UserAgent;
use LukaLtaApi\Value\GeoLocation;
use LukaLtaApi\Value\PerformanceMetrics;

class PageViewData
{
    public function __construct(
        private readonly string             $siteId,
        private readonly DateTimeImmutable  $occurredOn,
        private readonly PageInfo           $pageInfo,
        private readonly UserAgent          $userAgent,
        private readonly GeoLocation        $geoLocation,
        private readonly ScreenDimensions   $screenDimensions,
        private readonly UrlParameter       $urlParameters,
        private readonly Device             $deviceType,
        private readonly ?Properties        $props,
        private readonly PerformanceMetrics $performanceMetrics,
        private readonly string             $sessionId,
        private readonly string             $userId,
        private readonly ?string            $referrer,
        private readonly ?string            $channel,
        private readonly ?string           $language,
        private readonly ?string           $eventName,
        private readonly ?string $ipAddress,
        private readonly EventType         $eventType = EventType::PAGEVIEW,
    ) {
    }

    public static function from(
        string $siteId,
        DateTimeImmutable $occurredOn,
        PageInfo $pageInfo,
        UserAgent $userAgent,
        GeoLocation $geoLocation,
        ScreenDimensions $screenDimensions,
        UrlParameter $urlParameters,
        Device $deviceType,
        ?Properties $props,
        PerformanceMetrics $performanceMetrics,
        string $sessionId,
        string $userId,
        ?string $referrer,
        ?string $channel,
        ?string $language,
        ?string $eventName,
        ?string $ipAddress,
        EventType $eventType = EventType::PAGEVIEW,
    ): self {
        return new self(
            $siteId,
            $occurredOn,
            $pageInfo,
            $userAgent,
            $geoLocation,
            $screenDimensions,
            $urlParameters,
            $deviceType,
            $props,
            $performanceMetrics,
            $sessionId,
            $userId,
            $referrer,
            $channel,
            $language,
            $eventName,
            $ipAddress,
            $eventType,
        );
    }

    public function toArray(): array
    {
        return [

        ];
    }

    public function getSiteId(): string
    {
        return $this->siteId;
    }

    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function getPageInfo(): PageInfo
    {
        return $this->pageInfo;
    }

    public function getUserAgent(): UserAgent
    {
        return $this->userAgent;
    }

    public function getGeoLocation(): GeoLocation
    {
        return $this->geoLocation;
    }

    public function getScreenDimensions(): ScreenDimensions
    {
        return $this->screenDimensions;
    }

    public function getUrlParameters(): UrlParameter
    {
        return $this->urlParameters;
    }

    public function getDeviceType(): Device
    {
        return $this->deviceType;
    }

    public function getProps(): ?Properties
    {
        return $this->props;
    }

    public function getPerformanceMetrics(): PerformanceMetrics
    {
        return $this->performanceMetrics;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getEventName(): ?string
    {
        return $this->eventName;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getEventType(): EventType
    {
        return $this->eventType;
    }
}
