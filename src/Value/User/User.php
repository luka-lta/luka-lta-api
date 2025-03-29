<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\User;

use DateTimeImmutable;
use PermissionsModule\Value\Role;

class User
{
    private function __construct(
        private readonly ?UserId $userId,
        private ?Role $role,
        private UserEmail  $email,
        private UserPassword  $password,
        private ?string  $avatarUrl,
        private readonly DateTimeImmutable  $createdAt,
        private readonly ?DateTimeImmutable  $updatedAt,
    ) {
    }

    public static function create(
        string $email,
        string $password,
        Role $role = null,
    ): self {
        return new self(
            null,
            $role,
            UserEmail::from($email),
            UserPassword::fromPlain($password),
            null,
            new DateTimeImmutable(),
            null,
        );
    }

    public static function fromDatabase(array $row): self
    {
        $date = $row['updated_at'] === null ? null : new DateTimeImmutable($row['updated_at']);

        return new self(
            UserId::fromInt($row['user_id']),
            Role::fromDatabase($row['role']),
            UserEmail::from($row['email']),
            UserPassword::fromHash($row['password']),
            $row['avatar_url'],
            new DateTimeImmutable($row['created_at']),
            $date,
        );
    }

    public function toArray(): array
    {
        return  [
            'userId' => $this->userId?->asInt(),
            'email' => $this->email->getEmail(),
            'role' => $this->role?->toArray(),
            'avatarUrl' => $this->avatarUrl,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function getRole(): ?Role
    {
        return $this->role;
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

    public function setRole(?Role $role): void
    {
        $this->role = $role;
    }

    public function setEmail(UserEmail $email): void
    {
        $this->email = $email;
    }

    public function setPassword(UserPassword $password): void
    {
        $this->password = $password;
    }

    public function setAvatarUrl(?string $avatarUrl): void
    {
        $this->avatarUrl = $avatarUrl;
    }
}
