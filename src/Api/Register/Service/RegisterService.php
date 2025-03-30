<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Register\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\InvalidPreviewTokenException;
use LukaLtaApi\Exception\UserAlreadyExistsException;
use LukaLtaApi\Repository\PreviewTokenRepository;
use LukaLtaApi\Repository\UserRepository;
use LukaLtaApi\Service\PreviewTokenValidationService;
use LukaLtaApi\Service\UserValidationService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\User\User;
use LukaLtaApi\Value\User\UserEmail;
use Psr\Http\Message\ServerRequestInterface;

class RegisterService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserValidationService $userValidationService,
        private readonly PreviewTokenValidationService $previewTokenValidationService,
    ) {
    }

    public function registerUser(ServerRequestInterface $request): ApiResult
    {
        $body = $request->getParsedBody();
        $email = UserEmail::from($body['email']);
        $username = $body['username'];
        $password = $body['password'];
        $token = $body['previewToken'] ?? null;

        if (!$token) {
            return ApiResult::from(
                JsonResult::from('Previewtoken is required'),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        try {
            $this->previewTokenValidationService->validatePreviewToken($token);
        } catch (InvalidPreviewTokenException $e) {
            return ApiResult::from(
                JsonResult::from($e->getMessage()),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        try {
            $this->userValidationService->ensureUserDoesNotExists($email, $username);
        } catch (UserAlreadyExistsException $e) {
            return ApiResult::from(
                JsonResult::from($e->getMessage()),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        $this->userRepository->create(
            User::create(
                $email->getEmail(),
                $username,
                $password
            )
        );

        return ApiResult::from(
            JsonResult::from('User created'),
            StatusCodeInterface::STATUS_CREATED
        );
    }
}
