<?php

declare(strict_types=1);

namespace LukaLtaApi\Service;

class CryptService
{
    public function generateAnonymousId(string $ipAddress, string $userAgent): string
    {
        return hash('sha256', $ipAddress . $userAgent);
    }
}
