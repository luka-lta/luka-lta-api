<?php

namespace LukaLtaApi\Api\User\Update\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiAvatarUploadException;
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
        UserId       $userId,
        UserEmail    $email,
        UserPassword $password,
        array        $uploadedFiles,
    ): void {
        $user = $this->repository->findById($userId);

        $uploadDir = '/app/uploads/profile-pictures/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $uploadDir));
        }

        if ($user === null) {
            throw new ApiUserNotExistsException(
                sprintf('User with ID %s not found', $userId->asString()),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        $avatarUrl = $user->getAvatarUrl();

        if (isset($uploadedFiles['avatar'])) {
            $uploadedFile = $uploadedFiles['avatar'];

            if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
                throw new ApiAvatarUploadException(
                    'File upload error',
                    StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            $filename = $uploadDir . $userId->asString() . '.' . pathinfo(
                    $uploadedFile->getClientFilename(),
                    PATHINFO_EXTENSION
                );
            $uploadedFile->moveTo($filename);
            $avatarUrl = $filename;
        }

        $user->setEmail($email);
        $user->setPassword($password);
        $user->setAvatarUrl($avatarUrl);

        $this->repository->update($user);
    }
}
