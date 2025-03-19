<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Preview;

use DateTimeImmutable;
use LukaLtaApi\Value\User\UserId;

class PreviewToken
{
    private function __construct(
        private readonly string             $token,
        private readonly int                $maxUse,
        private readonly int                $used,
        private readonly bool               $isActive,
        private readonly UserId             $userId,
        private readonly ?DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        string $token,
        int    $maxUse,
        bool   $isActive,
        UserId $createdBy
    ): self {
        return new self(
            $token,
            $maxUse,
            0,
            $isActive,
            $createdBy,
            new DateTimeImmutable(),
        );
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            $row['token'],
            $row['max_use'],
            $row['used'],
            $row['is_active'],
            UserId::fromInt($row['created_by']),
            new DateTimeImmutable($row['created_at']),
        );
    }

    public static function generateToken(): string
    {
        return substr(
            str_shuffle(
                str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', 6)
            ), 0, 6
        );
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUsed(): int
    {
        return $this->used;
    }

    public function getMaxUse(): int
    {
        return $this->maxUse;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }
}
