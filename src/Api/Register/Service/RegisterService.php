<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Register\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\PreviewTokenRepository;
use LukaLtaApi\Repository\UserRepository;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\User\User;
use LukaLtaApi\Value\User\UserEmail;
use Psr\Http\Message\ServerRequestInterface;

class RegisterService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PreviewTokenRepository $tokenRepository,
    ) {
    }

    public function registerUser(ServerRequestInterface $request): ApiResult
    {
        $body = $request->getParsedBody();
        $email = UserEmail::from($body['email']);
        $password = $body['password'];
        $token = $body['previewToken'] ?? null;

        if (!$token) {
            return ApiResult::from(
                JsonResult::from('Previewtoken is required'),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        $token = $this->tokenRepository->getToken($token);

        if (!$token) {
            return ApiResult::from(
                JsonResult::from('Invalid preview token'),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        if ($token->isExpired()) {
            return ApiResult::from(
                JsonResult::from('Preview token is expired'),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        $token->useToken();
        $this->tokenRepository->updateToken($token);
        $user = $this->userRepository->findByEmail($email);

        if ($user) {
            return ApiResult::from(
                JsonResult::from('User already exists with this email'),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        $this->userRepository->create(
            User::create(
                $email->getEmail(),
                $password
            )
        );

        return ApiResult::from(
            JsonResult::from('User created'),
            StatusCodeInterface::STATUS_CREATED
        );
    }
}
