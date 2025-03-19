<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Preview;

use DateTimeImmutable;
use LukaLtaApi\Value\User\UserId;

class PreviewToken
{
    private function __construct(
        private readonly string $token,
        private readonly UserId $userId,
        private readonly ?DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(UserId $createdBy): self
    {
        return new self(
            rand
        )
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            $row['token'],
            UserId::fromInt($row['created_by']),
            new DateTimeImmutable($row['created_at']),
        );
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
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
