<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\User;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiValidationException;

class UserEmail
{
    private function __construct(
        private readonly string $email
    ) {
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiValidationException('Invalid email', StatusCodeInterface::STATUS_BAD_REQUEST);
        }
    }

    public static function from(string $email): self
    {
        return new self($email);
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
