<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Tracking;

class ClickMetadata
{
    private function __construct(
        private readonly ?Market $market,
        private readonly ?string $ipAddress,
        private readonly ?UserAgent $userAgent,
        private readonly ?string $referrer,
    ) {
    }

    public static function fromArray(array $raw): self
    {
        return new self(
            isset($raw['market']) ?
                Market::from($raw['market']) :
                null,
            isset($raw['ipAddress']) ?
                $raw['ipAddress'] :
                null,
            isset($raw['userAgent'])
                ? UserAgent::fromUserAgent($raw['userAgent']) :
                null,
            isset($raw['referrer']) ?
                $raw['referrer'] :
                null,
        );
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            isset($row['market']) ?
                Market::from($row['market']) :
                null,
            isset($row['ip_address']) ?
                $row['ip_address'] :
                null,
            isset($raw['user_agent'])
                ? UserAgent::fromUserAgent($row['user_agent']) :
                null,
            isset($row['referrer']) ?
                $row['referrer'] :
                null,
        );
    }

    public function getMarket(): ?Market
    {
        return $this->market;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?UserAgent
    {
        return $this->userAgent;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }
}
