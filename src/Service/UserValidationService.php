<?php

declare(strict_types=1);

namespace LukaLtaApi\Service;

use LukaLtaApi\Exception\UserAlreadyExistsException;
use LukaLtaApi\Repository\UserRepository;
use LukaLtaApi\Value\User\UserEmail;

class UserValidationService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function ensureUserDoesNotExists(UserEmail $email, string $username): void
    {
        if ($this->userRepository->findByEmail($email)) {
            throw new UserAlreadyExistsException('User already exists with this email');
        }

        if ($this->userRepository->findByUsername($username)) {
            throw new UserAlreadyExistsException('User already exists with this username');
        }
    }
}
