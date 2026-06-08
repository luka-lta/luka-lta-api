<?php

declare(strict_types=1);

namespace LukaLtaApi\Service\Contracts;

interface ChannelDetectorServiceInterface
{
    public function detectChannel(
        ?string $referrer,
        ?string $queryString,
        ?string $hostname,
    ): string;
}
