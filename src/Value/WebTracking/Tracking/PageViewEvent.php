<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\WebTracking\Tracking;

use LukaLtaApi\Value\PerformanceMetrics;

class PageViewEvent
{
    public function __construct(
        private readonly EventType $eventType,
        private readonly string $siteId,
        private readonly PageInfo $pageInfo,
        private readonly ScreenDimensions $screenDimensions,
        private readonly Properties $properties,
        private readonly PerformanceMetrics $performanceMetrics,
        private readonly ?string $language,
        private readonly ?string $referrer,
        private readonly ?string $eventName,
        private readonly ?string $userId,
        private readonly ?string $ipAddress,
        private readonly ?string $userAgent,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            EventType::from($payload['type']),
            $payload['site_id'],
            PageInfo::fromPayload($payload),
            ScreenDimensions::fromPayload($payload),
            Properties::fromPayload($payload),
            PerformanceMetrics::fromPayload($payload),
            isset($payload['language']) ? (string)$payload['language'] : null,
            isset($payload['referrer']) ? (string)$payload['referrer'] : null,
            isset($payload['event_name']) ? (string)$payload['event_name'] : null,
            isset($payload['user_id']) ? (string)$payload['user_id'] : null,
            isset($payload['ip_address']) ? (string)$payload['ip_address'] : null,
            isset($payload['user_agent']) ? (string)$payload['user_agent'] : null,
        );
    }

    public function getEventType(): EventType
    {
        return $this->eventType;
    }

    public function getSiteId(): string
    {
        return $this->siteId;
    }

    public function getPageInfo(): PageInfo
    {
        return $this->pageInfo;
    }

    public function getScreenDimensions(): ScreenDimensions
    {
        return $this->screenDimensions;
    }

    public function getProperties(): Properties
    {
        return $this->properties;
    }

    public function getPerformanceMetrics(): PerformanceMetrics
    {
        return $this->performanceMetrics;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function getEventName(): ?string
    {
        return $this->eventName;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }
}
