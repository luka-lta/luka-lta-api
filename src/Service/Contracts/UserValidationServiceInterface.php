<?php

declare(strict_types=1);

namespace LukaLtaApi\Service\Contracts;

use LukaLtaApi\Value\User\UserEmail;
use LukaLtaApi\Value\User\UserId;

interface UserValidationServiceInterface
{
    public function ensureUserDoesNotExists(
        UserEmail $email,
        string $username,
        ?UserId $excludeUserId = null,
    ): void;
}
