<?php

namespace LukaLtaApi\Service;

use LukaLtaApi\Repository\EnvironmentRepository;
use LukaLtaApi\Value\User\User;
use ReallySimpleJWT\Jwt;
use ReallySimpleJWT\Token;

class TokenService
{
    public function __construct(
        private readonly EnvironmentRepository $environmentRepository,
    ) {
    }

    public function generateToken(User $user): Jwt
    {
        $expiresIn = time() + (int)$this->environmentRepository->get('JWT_NORMAL_EXPIRATION_TIME');
        return Token::builder($this->environmentRepository->get('JWT_SECRET'))
            ->setIssuer('https://api.luka-lta.dev')
            ->setPayloadClaim('email', $user->getEmail()->asString())
            ->setPayloadClaim('sub', $user->getUserId()?->asString())
            ->setIssuedAt(time())
            ->setExpiration($expiresIn)
            ->build();
    }
}
