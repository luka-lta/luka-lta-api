<?php

namespace LukaLtaApi\Api\Auth\Service;

use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\UserRepository;
use LukaLtaApi\Service\TokenService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\User\UserEmail;
use ReallySimpleJWT\Token;

class AuthService
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly TokenService $tokenService,
    ) {
    }

    public function login(array $parsedBody): ApiResult
    {
        $email = UserEmail::from($parsedBody['email']);
        $password = $parsedBody['password'];

        $user = $this->repository->findByEmail($email);

        if ($user === null || $user->isActive() === false || $user->getPassword()->verify($password) === false) {
            return ApiResult::from(
                JsonResult::from('Authentication failed'),
                StatusCodeInterface::STATUS_UNAUTHORIZED
            );
        }

        $user->setLastActive(new DateTimeImmutable());
        $this->repository->update($user);

        $token = $this->tokenService->generateToken($user);

        return ApiResult::from(JsonResult::from('User logged in', [
            'token' => $token->getToken(),
            'user' => $user->toArray(),
        ]));
    }
}
