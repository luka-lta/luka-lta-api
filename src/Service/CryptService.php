<?php

declare(strict_types=1);

namespace LukaLtaApi\Service;

use LukaLtaApi\Service\Contracts\CryptServiceInterface;

class CryptService implements CryptServiceInterface
{
    public function generateAnonymousId(string $ipAddress, string $userAgent): string
    {
        $dailySalt = date('Y-m-d');
        return hash('sha256', $ipAddress . $userAgent . $dailySalt);
    }
}
