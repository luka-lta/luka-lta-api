<?php

namespace LukaLtaApi\Api\Auth\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiAuthException;
use LukaLtaApi\Exception\ApiUserNotExistsException;
use LukaLtaApi\Repository\UserRepository;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\User\UserEmail;
use ReallySimpleJWT\Token;

class AuthService
{
    public function __construct(
        private readonly UserRepository $repository,
    ) {
    }

    public function login(UserEmail $email, string $password): ApiResult
    {
        $user = $this->repository->findByEmail($email);

        if ($user === null) {
            return ApiResult::from(
                JsonResult::from('User not found'),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        if (!$user->getPassword()->verify($password)) {
            return ApiResult::from(
                JsonResult::from('Invalid password'),
                StatusCodeInterface::STATUS_UNAUTHORIZED
            );
        }

        $expiresIn = time() + (int)getenv('JWT_NORMAL_EXPIRATION_TIME');
        $token = Token::builder(getenv('JWT_SECRET'))
            ->setIssuer('https://api.luka-lta.dev')
            ->setPayloadClaim('email', $user->getEmail()->getEmail())
            ->setPayloadClaim('sub', $user->getUserId()?->asString())
            ->setIssuedAt(time())
            ->setExpiration($expiresIn)
            ->build();

        return ApiResult::from(JsonResult::from('User logged in', [
            'token' => $token->getToken(),
            'user' => $user->toArray(),
        ]));
    }
}
