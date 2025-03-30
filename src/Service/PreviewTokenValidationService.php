<?php

declare(strict_types=1);

namespace LukaLtaApi\Service;

use LukaLtaApi\Exception\InvalidPreviewTokenException;
use LukaLtaApi\Repository\PreviewTokenRepository;
use LukaLtaApi\Value\Preview\PreviewToken;

class PreviewTokenValidationService
{
    public function __construct(
        private readonly PreviewTokenRepository $tokenRepository,
    ) {
    }

    public function validatePreviewToken(string $token): void
    {
        $token = $this->tokenRepository->getToken($token);

        if (!$token) {
            throw new InvalidPreviewTokenException('Invalid preview token');
        }

        if ($token->isExpired()) {
            throw new InvalidPreviewTokenException('Preview token is expired');
        }

        $token->useToken();
        $this->tokenRepository->updateToken($token);
    }
}
