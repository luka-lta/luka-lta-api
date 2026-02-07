<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Tracking;

use DateTimeImmutable;
use DateTimeZone;

class TrackingSession
{
    private function __construct(
        private readonly string $sessionId,
        private readonly int $siteId,
        private readonly string $userId,
        private readonly DateTimeImmutable $startTime,
        private readonly DateTimeImmutable $lastActivityTime,
    ) {
    }

    public static function from(
        string $sessionId,
        int $siteId,
        string $userId,
        DateTimeImmutable $startTime = new DateTimeImmutable('now'),
        DateTimeImmutable $lastActivityTime = new DateTimeImmutable('now')
    ): self {
        return new self($sessionId, $siteId, $userId, $startTime, $lastActivityTime);
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            $row['session_id'],
            $row['site_id'],
            $row['user_id'],
            new DateTimeImmutable($row['start_time'], new DateTimeZone('UTC')),
            new DateTimeImmutable($row['last_activity'], new DateTimeZone('UTC')),
        );
    }

    public function toArray(): array
    {
        return [
            'sessionId' => $this->sessionId,
            'siteId' => $this->siteId,
            'userId' => $this->userId,
            'startTime' => $this->startTime->format(DATE_ATOM),
            'lastActivityTime' => $this->lastActivityTime->format(DATE_ATOM),
        ];
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getSiteId(): int
    {
        return $this->siteId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getStartTime(): DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getLastActivityTime(): DateTimeImmutable
    {
        return $this->lastActivityTime;
    }
}
