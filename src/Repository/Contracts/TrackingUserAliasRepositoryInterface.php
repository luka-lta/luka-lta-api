<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository\Contracts;

use LukaLtaApi\Value\Tracking\User\TrackingUser;

interface TrackingUserAliasRepositoryInterface
{
    public function getUserAlias(int $siteId, string $anonymousId): array|false;

    public function insertUserAlias(TrackingUser $user): void;
}
