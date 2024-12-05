<?php

namespace LukaLtaApi\Api\User\Update\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiUserNotExistsException;
use LukaLtaApi\Repository\UserRepository;
use LukaLtaApi\Value\User\UserEmail;
use LukaLtaApi\Value\User\UserId;
use LukaLtaApi\Value\User\UserPassword;

class UserUpdateService
{
    public function __construct(
        private readonly UserRepository $repository
    ) {
    }

    public function update(
        UserId $userId,
        UserEmail $email,
        UserPassword $password,
        ?string $avatarUrl
    ): void {
        $user = $this->repository->findById($userId);

        if ($user === null) {
            throw new ApiUserNotExistsException(
                sprintf('User with ID %s not found', $userId->asString()),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        $user->setEmail($email);
        $user->setPassword($password);
        $user->setAvatarUrl($avatarUrl);

        $this->repository->update($user);
    }
}
