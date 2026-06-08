<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository\Contracts;

interface TrackingUserRepositoryInterface
{
    public function getAllTrackingUsers(int $siteId, int $limit, int $offset): ?array;

    public function getTrackingUser(int $siteId, string $userId): ?array;
}
