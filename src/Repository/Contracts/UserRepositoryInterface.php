<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository\Contracts;

use LukaLtaApi\Api\User\Value\UserExtraFilter;
use LukaLtaApi\Value\User\User;
use LukaLtaApi\Value\User\UserEmail;
use LukaLtaApi\Value\User\UserId;
use LukaLtaApi\Value\User\Users;

interface UserRepositoryInterface
{
    public function create(User $user): void;

    public function update(User $user): void;

    public function findByEmail(UserEmail $email, ?UserId $excludeUserId = null): ?User;

    public function findById(UserId $userId): ?User;

    public function findByUsername(string $username, ?UserId $excludeUserId = null): ?User;

    public function getAll(UserExtraFilter $filter): Users;

    public function deleteUser(UserId $userId): void;
}
