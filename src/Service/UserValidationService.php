<?php

declare(strict_types=1);

namespace LukaLtaApi\Service;

use LukaLtaApi\Exception\UserAlreadyExistsException;
use LukaLtaApi\Repository\UserRepository;
use LukaLtaApi\Value\User\UserEmail;
use LukaLtaApi\Value\User\UserId;

class UserValidationService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function ensureUserDoesNotExists(
        UserEmail $email,
        string $username,
        ?UserId $excludeUserId = null,
    ): void {
        if ($this->userRepository->findByEmail($email, $excludeUserId)) {
            throw new UserAlreadyExistsException('User already exists with this email');
        }

        if ($this->userRepository->findByUsername($username, $excludeUserId)) {
            throw new UserAlreadyExistsException('User already exists with this username');
        }
    }
}
