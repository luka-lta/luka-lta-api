<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\WebTracking\Tracking;

class PageInfo
{
    private function __construct(
        private readonly ?string $hostname,
        private readonly ?string $pathName,
        private readonly ?string $queryString,
        private readonly ?string $pageTitle,
    ) {
    }

    public static function from(
        ?string $hostname,
        ?string $pathName,
        ?string $queryString,
        ?string $pageTitle,
    ): self {
        return new self(
            $hostname,
            $pathName,
            $queryString,
            $pageTitle,
        );
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            isset($payload['hostname']) ? (string)$payload['hostname'] : null,
            isset($payload['pathname']) ? (string)$payload['pathname'] : null,
            isset($payload['querystring']) ? (string)$payload['querystring'] : null,
            isset($payload['pageTitle']) ? (string)$payload['pageTitle'] : null,
        );
    }

    public function getHostname(): ?string
    {
        return $this->hostname;
    }

    public function getPathName(): ?string
    {
        return $this->pathName;
    }

    public function getQueryString(): ?string
    {
        return $this->queryString;
    }

    public function getPageTitle(): ?string
    {
        return $this->pageTitle;
    }
}
