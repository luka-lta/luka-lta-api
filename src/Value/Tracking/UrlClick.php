<?php

namespace LukaLtaApi\Value\Tracking;

use DateTimeImmutable;
use LukaLtaApi\Value\LinkCollection\LinkUrl;

class UrlClick
{
    private function __construct(
        private readonly ?int $clickId,
        private readonly LinkUrl $url,
        private readonly ?DateTimeImmutable $clickedAt,
        private readonly ?string $ipAdress,
        private readonly ?string $userAgent,
        private readonly ?string $referer,
    ) {
    }

    public static function from(
        ?int $clickId,
        LinkUrl $url,
        ?DateTimeImmutable $clickedAt,
        ?string $ipAdress,
        ?string $userAgent,
        ?string $referer,
    ): self {
        return new self(
            $clickId,
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
            $data['click_id'],
            LinkUrl::fromString($data['url']),
            new DateTimeImmutable($data['clicked_at']),
            $data['ip_adress'],
            $data['user_agent'],
            $data['referer'],
        );
    }

    public function toArray(): array
    {
        return [
            'clickId' => $this->clickId,
            'url' => (string)$this->url,
            'clickedAt' => $this->clickedAt->format('Y-m-d H:i:s'),
            'ipAdress' => $this->ipAdress,
            'userAgent' => $this->userAgent,
            'referer' => $this->referer,
        ];
    }

    public function getClickId(): ?int
    {
        return $this->clickId;
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
