<?php

namespace LukaLtaApi\Value\Tracking;

use DateTimeImmutable;

class UrlClick
{
    private function __construct(
        private readonly ?int $clickId,
        private readonly string $url,
        private readonly ?DateTimeImmutable $clickedAt,
        private readonly ?string $ipAdress,
        private readonly ?string $userAgent,
        private readonly ?string $referer,
    ) {
    }

    public static function from(
        ?int $clickId,
        string $url,
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
            $data['url'],
            new DateTimeImmutable($data['clicked_at']),
            $data['ip_adress'],
            $data['user_agent'],
            $data['referer'],
        );
    }

    public function toArray(): array
    {
        return [
            'click_id' => $this->clickId,
            'url' => $this->url,
            'clicked_at' => $this->clickedAt->format('Y-m-d H:i:s'),
            'ip_adress' => $this->ipAdress,
            'user_agent' => $this->userAgent,
            'referer' => $this->referer,
        ];
    }

    public function getClickId(): ?int
    {
        return $this->clickId;
    }

    public function getUrl(): string
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
