<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\SelfUser\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiAvatarUploadException;
use LukaLtaApi\Exception\UserAlreadyExistsException;
use LukaLtaApi\Repository\UserRepository;
use LukaLtaApi\Service\AvatarService;
use LukaLtaApi\Service\UserValidationService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\User\UserEmail;
use LukaLtaApi\Value\User\UserId;
use Psr\Http\Message\ServerRequestInterface;

class SelfUserService
{
    public function __construct(
        private readonly UserRepository        $repository,
        private readonly UserValidationService $validationService,
        private readonly AvatarService         $avatarService,
    ) {
    }

    public function getUser(ServerRequestInterface $request): ApiResult
    {
        $userId = UserId::fromString($request->getAttribute('userId'));

        $user = $this->repository->findById($userId);

        if ($user === null) {
            return ApiResult::from(
                JsonResult::from('User not found'),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        return ApiResult::from(
            JsonResult::from('User found', ['user' => $user->toArray()]),
        );
    }

    public function updateUser(ServerRequestInterface $request): ApiResult
    {
        $userId = UserId::fromString($request->getAttribute('userId'));
        $requestedUserId = UserId::fromInt($request->getParsedBody()['userId']);

        if ($userId->asInt() !== $requestedUserId->asInt()) {
            return $this->denieRequest();
        }

        $uploadedFiles = $request->getUploadedFiles();
        $body = $request->getParsedBody();
        $email = UserEmail::from($body['email']);
        $username = $body['username'];

        $user = $this->repository->findById($userId);
        if ($user === null) {
            return ApiResult::from(
                JsonResult::from('User not found'),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        try {
            if ($user->getEmail()->getEmail() !== $email->getEmail() || $user->getUsername() !== $username) {
                $this->validationService->ensureUserDoesNotExists($email, $username);
            }
        } catch (UserAlreadyExistsException $e) {
            return ApiResult::from(
                JsonResult::from($e->getMessage()),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        $avatarUrl = $user->getAvatarUrl();
        if (isset($uploadedFiles['avatar'])) {
            $uploadedFile = $uploadedFiles['avatar'];
            try {
                $avatarUrl = $this->avatarService->uploadAvatar($uploadedFile, $userId);
            } catch (ApiAvatarUploadException $e) {
                return ApiResult::from(
                    JsonResult::from($e->getMessage()),
                    $e->getCode()
                );
            }
        }

        $user->setUsername($username);
        $user->setEmail($email);
        $user->setAvatarUrl($avatarUrl);

        $this->repository->update($user);

        return ApiResult::from(
            JsonResult::from('User updated')
        );
    }

    private function denieRequest(): ApiResult
    {
        return ApiResult::from(
            JsonResult::from('You are not allowed to update this user'),
            StatusCodeInterface::STATUS_UNAUTHORIZED
        );
    }
}
