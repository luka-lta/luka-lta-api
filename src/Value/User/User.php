<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\User;

use DateTimeImmutable;

class User
{
    private function __construct(
        private readonly ?UserId $userId,
        private readonly UserEmail  $email,
        private readonly UserPassword  $password,
        private readonly string  $avatarUrl,
        private readonly DateTimeImmutable  $createdAt,
        private readonly ?DateTimeImmutable  $updatedAt,
    ) {
    }

    public static function create(
        string $email,
        string $password,
        string $avatarUrl,
    ): self {
        return new self(
            null,
            UserEmail::from($email),
            UserPassword::fromPlain($password),
            $avatarUrl,
            new DateTimeImmutable(),
            null,
        );
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            UserId::fromInt($row['user_id']),
            UserEmail::from($row['email']),
            UserPassword::fromHash($row['password']),
            $row['avatar_url'],
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at']),
        );
    }

    public function getAvatarUrl(): string
    {
        return $this->avatarUrl;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEmail(): UserEmail
    {
        return $this->email;
    }

    public function getPassword(): UserPassword
    {
        return $this->password;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getUserId(): ?UserId
    {
        return $this->userId;
    }
}
