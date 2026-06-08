<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository\Contracts;

use LukaLtaApi\Value\Preview\PreviewToken;
use LukaLtaApi\Value\Preview\PreviewTokens;

interface PreviewTokenRepositoryInterface
{
    public function createToken(PreviewToken $token): void;

    public function listTokens(): PreviewTokens;

    public function updateToken(PreviewToken $token): void;

    public function getToken(string $tokenId): ?PreviewToken;

    public function deleteToken(string $tokenId): void;
}
