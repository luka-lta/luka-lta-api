<?php

namespace LukaLtaApi\Service;

use LukaLtaApi\Value\User\User;
use ReallySimpleJWT\Jwt;
use ReallySimpleJWT\Token;

class TokenService
{
    public function generateToken(User $user): Jwt
    {
        $expiresIn = time() + (int)getenv('JWT_NORMAL_EXPIRATION_TIME');
        return Token::builder(getenv('JWT_SECRET'))
            ->setIssuer('https://api.luka-lta.dev')
            ->setPayloadClaim('email', $user->getEmail()->getEmail())
            ->setPayloadClaim('sub', $user->getUserId()?->asString())
            ->setIssuedAt(time())
            ->setExpiration($expiresIn)
            ->build();
    }
}
