<?php

namespace LukaLtaApi\Value\WebTracking\Site;

use DateTimeImmutable;
use LukaLtaApi\Value\User\UserId;
use LukaLtaApi\Value\WebTracking\Config\SiteConfig;

class Site
{
    private function __construct(
        private readonly string $siteId,
        private readonly string $name,
        private readonly string $domain,
        private readonly ?DateTimeImmutable $createdAt,
        private readonly ?DateTimeImmutable $updatedAt,
        private readonly UserId $createdBy,
        private readonly bool $public,
        private readonly bool $blockBots,
        private readonly array $excludedIps,
        private readonly array $excludedCountries,
        private readonly SiteConfig $siteConfig,
        private readonly bool $trackIp,
    ) {
    }

    public static function fromDatabase(array $row): self
    {
        $createdAt = $row['created_at'] === null ? null : new DateTimeImmutable($row['created_at']);
        $updatedAt = $row['updated_at'] === null ? null : new DateTimeImmutable($row['updated_at']);

        return new self(
            $row['site_id'],
            $row['name'],
            $row['domain'],
            $createdAt,
            $updatedAt,
            UserId::fromString($row['created_by']),
            (bool)$row['public'],
            (bool)$row['block_bots'],
            json_decode($row['excluded_ips']),
            json_decode($row['excluded_countries']),
            SiteConfig::from(
                $row['web_vitals'],
                $row['track_errors'],
                $row['track_outbound'],
                $row['track_url_params'],
                $row['track_initial'],
                $row['track_spa_navigation'],
            ),
            $row['track_ip'],
        );
    }

    public function getSiteId(): string
    {
        return $this->siteId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCreatedBy(): UserId
    {
        return $this->createdBy;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function isBlockBots(): bool
    {
        return $this->blockBots;
    }

    public function getExcludedIps(): array
    {
        return $this->excludedIps;
    }

    public function getExcludedCountries(): array
    {
        return $this->excludedCountries;
    }

    public function getSiteConfig(): SiteConfig
    {
        return $this->siteConfig;
    }

    public function isTrackIp(): bool
    {
        return $this->trackIp;
    }
}
