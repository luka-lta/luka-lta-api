<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository\Contracts;

use LukaLtaApi\QueryBuilder\Value\QueryContext;
use LukaLtaApi\Value\Tracking\TrackingSession;

interface SessionRepositoryInterface
{
    public function getExistingSession(string $userId, int $siteId): ?TrackingSession;

    public function updateSession(string $userId, int $siteId): string;

    public function cleanupSessions(): void;

    public function getSessionsFromTrackingUser(string $trackingUserId, QueryContext $queryContext): ?array;

    public function getSession(int $siteId, string $sessionId, int $limit, int $offset): array;
}
