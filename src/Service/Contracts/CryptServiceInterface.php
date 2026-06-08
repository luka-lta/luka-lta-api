<?php

declare(strict_types=1);

namespace LukaLtaApi\Service\Contracts;

interface CryptServiceInterface
{
    public function generateAnonymousId(string $ipAddress, string $userAgent): string;
}
