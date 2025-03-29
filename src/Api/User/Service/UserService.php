<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\User\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Api\User\Value\UserExtraFilter;
use LukaLtaApi\Exception\ApiAvatarUploadException;
use LukaLtaApi\Repository\UserRepository;
use LukaLtaApi\Service\AvatarService;
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
        private readonly AvatarService $avatarService,
    ) {
    }

    public function createUser(array $body): ApiResult
    {
        $email = $body['email'];
        $password = $body['password'];

        if ($this->repository->findByEmail(UserEmail::from($email)) !== null) {
            return ApiResult::from(
                JsonResult::from('User already exists with this email'),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        $this->repository->create(
            User::create(
                $email,
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

        $user = $this->repository->findById($userId);

        if ($user === null) {
            return ApiResult::from(
                JsonResult::from('User not found'),
                StatusCodeInterface::STATUS_NOT_FOUND
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
