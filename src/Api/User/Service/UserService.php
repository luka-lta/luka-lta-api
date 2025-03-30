<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\User\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Api\User\Value\UserExtraFilter;
use LukaLtaApi\Exception\ApiAvatarUploadException;
use LukaLtaApi\Exception\UserAlreadyExistsException;
use LukaLtaApi\Repository\UserRepository;
use LukaLtaApi\Service\AvatarService;
use LukaLtaApi\Service\UserValidationService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\User\User;
use LukaLtaApi\Value\User\UserEmail;
use LukaLtaApi\Value\User\UserId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

class UserService
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly UserValidationService $validationService,
        private readonly AvatarService $avatarService,
    ) {
    }

    public function createUser(array $body): ApiResult
    {
        $username = $body['username'];
        $email = UserEmail::from($body['email']);
        $password = $body['password'];

        try {
            $this->validationService->ensureUserDoesNotExists($email, $username);
        } catch (UserAlreadyExistsException $e) {
            return ApiResult::from(
                JsonResult::from($e->getMessage()),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        $this->repository->create(
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

    public function updateUser(ServerRequestInterface $request): ApiResult
    {
        $uploadedFiles = $request->getUploadedFiles();
        $body = $request->getParsedBody();
        $userId = UserId::fromString($request->getAttribute('userId'));
        $email = UserEmail::from($body['email']);
        $username = $body['username'];
        $isActive = $body['is_active'];

        $user = $this->repository->findById($userId);

        if ($user === null) {
            return ApiResult::from(
                JsonResult::from('User not found'),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        try {
            $this->validationService->ensureUserDoesNotExists($email, $username);
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
        $user->setIsActive($isActive);
        $user->setEmail($email);
        $user->setAvatarUrl($avatarUrl);

        $this->repository->update($user);

        return ApiResult::from(
            JsonResult::from('User updated')
        );
    }

    public function getAllUsers(ServerRequestInterface $request): ApiResult
    {
        $filter = UserExtraFilter::parseFromQuery($request->getQueryParams());

        $users = $this->repository->getAll($filter);

        if ($users->count() === 0) {
            return ApiResult::from(
                JsonResult::from('No users found', ['users' => []]),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        return ApiResult::from(
            JsonResult::from('Users fetched successfully', ['users' => $users->toArray()])
        );
    }

    public function getAvatar(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $uploadDir = '/app/uploads/profile-pictures/';
        $filename = $request->getAttribute('filename');

        $file = $uploadDir . $filename;

        if (!file_exists($file)) {
            return ApiResult::from(
                JsonResult::from('File not found'),
                StatusCodeInterface::STATUS_NOT_FOUND
            )->getResponse($response);
        }

        $mimeType = mime_content_type($file);
        $response->getBody()->write(file_get_contents($file));

        return $response
            ->withHeader('Content-Type', $mimeType);
    }
}
