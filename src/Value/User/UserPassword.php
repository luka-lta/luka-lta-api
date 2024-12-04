<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\User;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiValidationException;

class UserPassword
{
    private function __construct(
        private readonly string $password,
    ) {
    }

    public static function fromPlain(string $plainPassword): self
    {
        if (strlen($plainPassword) < 8) {
            throw new ApiValidationException(
                'Password must be at least 8 characters long',
                StatusCodeInterface::STATUS_BAD_REQUEST,
            );
        }

        if (strlen($plainPassword) > 64) {
            throw new ApiValidationException(
                'Password must be less than 64 characters long',
                StatusCodeInterface::STATUS_BAD_REQUEST,
            );
        }

        $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        return new self($hashedPassword);
    }

    public static function fromHash(string $hashedPassword): self
    {
        return new self($hashedPassword);
    }

    public function verify(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->password);
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
