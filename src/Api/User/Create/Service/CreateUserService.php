<?php

namespace LukaLtaApi\Api\User\Create\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiUserAlreadyExistsException;
use LukaLtaApi\Repository\UserRepository;
use LukaLtaApi\Value\User\User;
use LukaLtaApi\Value\User\UserEmail;
use LukaLtaApi\Value\User\UserPassword;

class CreateUserService
{
    public function __construct(
        private readonly UserRepository $repository,
    ) {
    }

    public function createUser(UserEmail $email, UserPassword $password): void
    {
        if ($this->repository->findUserByEmail($email) !== null) {
            throw new ApiUserAlreadyExistsException(
                'User already exists with this email',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        $this->repository->createUser(
            User::create(
                $email->getEmail(),
                $password->getPassword()
            )
        );
    }
}
