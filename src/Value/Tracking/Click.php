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
        private readonly ClickMetadata      $metadata,
    ) {
    }

    public static function from(
        ?ClickId $clickId,
        ClickTag $tag,
        LinkUrl $url,
        ?DateTimeImmutable $clickedAt,
        ClickMetadata $clickMetadata,
    ): self {
        return new self(
            $clickId,
            $tag,
            $url,
            $clickedAt,
            $clickMetadata,
        );
    }

    public static function fromDatabase(array $data): self
    {
        return new self(
            ClickId::fromInt($data['click_id']),
            ClickTag::from($data['click_tag']),
            LinkUrl::fromString($data['url']),
            new DateTimeImmutable($data['clicked_at']),
            ClickMetadata::fromDatabase($data)
        );
    }

    public function toArray(): array
    {
        return [
            'clickId' => $this->clickId->asInt(),
            'clickTag' => $this->tag->asString(),
            'url' => (string)$this->url,
            'clickedAt' => $this->clickedAt->format('Y-m-d H:i:s'),
            'ipAddress' => $this->metadata->getIpAddress(),
            'market' => $this->metadata->getMarket()?->asString(),
            'userAgent' => $this->metadata->getUserAgent()?->asString(),
            'os' => $this->metadata->getUserAgent()?->getOs(),
            'device' => $this->metadata->getUserAgent()?->getDevice(),
            'referer' => $this->metadata->getReferrer(),
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

    public function getMetadata(): ClickMetadata
    {
        return $this->metadata;
    }
}
