<?php

declare(strict_types=1);

namespace LukaLtaApi\Service\Contracts;

use LukaLtaApi\Value\User\User;
use ReallySimpleJWT\Jwt;

interface TokenServiceInterface
{
    public function generateToken(User $user): Jwt;
}
