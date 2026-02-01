<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Tracking\User;

class TrackingUser
{
    private function __construct(
        private readonly int $siteId,
        private readonly string $anonymousId,
        private readonly string $userId,
    ) {
    }

    public static function from(
        int $siteId,
        string $anonymousId,
        string $userId,
    ): self {
        return new self($siteId, $anonymousId, $userId);
    }

    public function getSiteId(): int
    {
        return $this->siteId;
    }

    public function getAnonymousId(): string
    {
        return $this->anonymousId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }
}
