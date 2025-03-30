<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\User;

use DateTimeImmutable;

class User
{
    private function __construct(
        private readonly ?UserId $userId,
        private string $username,
        private UserEmail  $email,
        private UserPassword  $password,
        private ?string  $avatarUrl,
        private bool $isActive,
        private ?DateTimeImmutable $lastActive,
        private readonly DateTimeImmutable  $createdAt,
        private readonly ?DateTimeImmutable  $updatedAt,
    ) {
    }

    public static function create(
        string $email,
        string $username,
        string $password,
        bool $isActive = true,
    ): self {
        return new self(
            null,
            $username,
            UserEmail::from($email),
            UserPassword::fromPlain($password),
            null,
            $isActive,
            null,
            new DateTimeImmutable(),
            null,
        );
    }

    public static function fromDatabase(array $row): self
    {
        $updatedAt = $row['updated_at'] === null ? null : new DateTimeImmutable($row['updated_at']);
        $lastActive = $row['last_active'] === null ? null : new DateTimeImmutable($row['last_active']);

        return new self(
            UserId::fromInt($row['user_id']),
            $row['username'],
            UserEmail::from($row['email']),
            UserPassword::fromHash($row['password']),
            $row['avatar_url'],
            (bool) $row['is_active'],
            $lastActive,
            new DateTimeImmutable($row['created_at']),
            $updatedAt,
        );
    }

    public function toArray(): array
    {
        return  [
            'userId' => $this->userId?->asInt(),
            'username' => $this->username,
            'email' => $this->email->getEmail(),
            'avatarUrl' => $this->avatarUrl,
            'isActive' => $this->isActive,
            'lastActive' => $this->lastActive?->format('Y-m-d H:i:s'),
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    public function getAvatarUrl(bool $withFullUrl = false): ?string
    {
        if ($withFullUrl && $this->avatarUrl !== null) {
            return 'https://api.luka-lta.dev/api/v1/avatar/' . $this->userId->asInt();
        }

        return $this->avatarUrl;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUsername(): string
    {
        return $this->username;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getLastActive(): ?DateTimeImmutable
    {
        return $this->lastActive;
    }

    public function getUserId(): ?UserId
    {
        return $this->userId;
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

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function setLastActive(?DateTimeImmutable $lastActive): void
    {
        $this->lastActive = $lastActive;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }
}
