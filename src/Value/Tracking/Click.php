<?php

namespace LukaLtaApi\Value\Tracking;

use DateTimeImmutable;
use LukaLtaApi\Value\LinkCollection\LinkUrl;

class Click
{
    private function __construct(
        private readonly ?ClickId $clickId,
        private readonly ClickTag $tag,
        private readonly LinkUrl $url,
        private readonly ?DateTimeImmutable $clickedAt,
        private readonly ?string $ipAdress,
        private readonly ?string $userAgent,
        private readonly ?string $referer,
    ) {
    }

    public static function from(
        ?ClickId $clickId,
        ClickTag $tag,
        LinkUrl $url,
        ?DateTimeImmutable $clickedAt,
        ?string $ipAdress,
        ?string $userAgent,
        ?string $referer,
    ): self {
        return new self(
            $clickId,
            $tag,
            $url,
            $clickedAt,
            $ipAdress,
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
            $data['ip_adress'] ?? null,
            $data['user_agent'] ?? null,
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
            'ipAdress' => $this->ipAdress,
            'userAgent' => $this->userAgent,
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

    public function getIpAdress(): ?string
    {
        return $this->ipAdress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getReferer(): ?string
    {
        return $this->referer;
    }
}
