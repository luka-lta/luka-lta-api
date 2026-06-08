<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository\Contracts;

use LukaLtaApi\Value\GeoLocation;

interface GeoLocationRepositoryInterface
{
    public function findByIp(string $ipAddress): GeoLocation;
}
