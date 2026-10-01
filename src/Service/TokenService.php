<?php

namespace LukaLtaApi\Service;

use LukaLtaApi\Repository\EnvironmentRepository;
use LukaLtaApi\Value\User\User;
use ReallySimpleJWT\Token;

class TokenService
{
    public function __construct(
        private readonly EnvironmentRepository $environmentRepository,
    ) {
    }

    public function generateToken(User $user): string
    {
        $secret = $this->environmentRepository->get('JWT_SECRET');
        $expiration = time() + (int) $this->environmentRepository->get('JWT_NORMAL_EXPIRATION_TIME', '86400');

        return Token::create(
            $user->getUserId()->asString(),
            $secret,
            $expiration,
            'backend.luka-lta.dev',
        );
    }
}
