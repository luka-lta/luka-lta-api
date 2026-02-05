<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Value\Tracking\User\TrackingUser;
use PDO;

class TrackingUserRepository
{
    private function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function insertTrackingUser(TrackingUser $trackingUser): void
    {

    }
}
