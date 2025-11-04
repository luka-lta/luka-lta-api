<?php

namespace LukaLtaApi\Value\Tracking;

use DateTimeImmutable;
use LukaLtaApi\Value\LinkCollection\LinkUrl;

class Click
{
    private function __construct(
        private readonly ?ClickId           $clickId,
        private readonly ClickTag           $tag,
        private readonly LinkUrl            $url,
        private readonly ?DateTimeImmutable $clickedAt,
        private readonly ?string            $ipAddress,
        private readonly ?string            $market,
        private readonly ?UserAgent         $userAgent,
        private readonly ?string            $referer,
    ) {
    }

    public static function from(
        ?ClickId $clickId,
        ClickTag $tag,
        LinkUrl $url,
        ?DateTimeImmutable $clickedAt,
        ?string $ipAddress,
        ?string $market,
        ?string $userAgent,
        ?string $referer,
    ): self {
        return new self(
            $clickId,
            $tag,
            $url,
            $clickedAt,
            $ipAddress,
            $market,
            $userAgent,
            $referer,
        );
    }

    public static function fromDatabase(array $data): self
    {
        return new self(
            ClickId::fromInt($data['click_id']),
            ClickTag::fromString($data['click_tag']),
            LinkUrl::fromString($data['url']),
            new DateTimeImmutable($data['clicked_at']),
            $data['ip_address'] ?? null,
            $data['market'] ?? null,
            isset($payload['user_agent']) ?
                UserAgent::from($payload['user_agent'], $payload['os'],$payload['device']) :
                null,
            $data['referer'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'clickId' => $this->clickId->asInt(),
            'clickTag' => $this->tag->getValue(),
            'url' => (string)$this->url,
            'clickedAt' => $this->clickedAt->format('Y-m-d H:i:s'),
            'ipAddress' => $this->ipAddress,
            'market' => $this->market,
            'userAgent' => $this->userAgent?->getRawUserAgent(),
            'os' => $this->userAgent?->getOs(),
            'device' => $this->userAgent?->getDevice(),
            'referer' => $this->referer,
        ];
    }

    public function getClickId(): ?ClickId
    {
        return $this->clickId;
    }

    public function getTag(): ClickTag
    {
        return $this->tag;
    }

    public function getUrl(): LinkUrl
    {
        return $this->url;
    }

    public function getClickedAt(): ?DateTimeImmutable
    {
        return $this->clickedAt;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getMarket(): ?string
    {
        return $this->market;
    }

    public function getUserAgent(): ?UserAgent
    {
        return $this->userAgent;
    }

    public function getReferer(): ?string
    {
        return $this->referer;
    }
}
