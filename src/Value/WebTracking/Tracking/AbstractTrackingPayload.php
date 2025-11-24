<?php

namespace LukaLtaApi\Value\WebTracking\Tracking;

use LukaLtaApi\Value\Tracking\UserAgent;
use LukaLtaApi\Value\User\UserId;

abstract class AbstractTrackingPayload
{
    public function __construct(
        protected int $siteId,
        protected EventType $eventType,
        protected ?string $hostname,
        protected ?string $pathname,
        protected ?string $queryString,
        protected ?ScreenDimensions $screenDimensions,
        protected ?string $language,
        protected ?string $pageTitle,
        protected ?string $referrer,
        protected ?UserId $userId,
        protected ?string $ipAddress,
        protected ?UserAgent $userAgent,
    ) {
    }

    abstract public static function fromPayload(array $payload): self;

    public function getSiteId(): int
    {
        return $this->siteId;
    }

    public function getEventType(): EventType
    {
        return $this->eventType;
    }

    public function getHostname(): ?string
    {
        return $this->hostname;
    }

    public function getPathname(): ?string
    {
        return $this->pathname;
    }

    public function getQueryString(): ?string
    {
        return $this->queryString;
    }

    public function getScreenDimensions(): ?ScreenDimensions
    {
        return $this->screenDimensions;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getPageTitle(): ?string
    {
        return $this->pageTitle;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function getUserId(): ?UserId
    {
        return $this->userId;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?UserAgent
    {
        return $this->userAgent;
    }
}