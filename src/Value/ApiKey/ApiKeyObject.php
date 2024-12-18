<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\ApiKey;

use DateTimeImmutable;
use LukaLtaApi\Value\User\UserId;

class ApiKeyObject
{
    private function __construct(
        private readonly ?KeyId $keyId,
        private KeyOrigin $origin,
        private readonly UserId $createdBy,
        private readonly DateTimeImmutable $createdAt,
        private readonly ?DateTimeImmutable $expiresAt,
        private readonly ApiKey $apiKey,
    ) {
    }

    public static function create(
        string $origin,
        int $createdBy,
        string $createdAt,
        ?string $expiresAt,
    ): self {
        $expires = $expiresAt === null ? null : new DateTimeImmutable($expiresAt);

        return new self(
            null,
            KeyOrigin::fromString($origin),
            UserId::fromInt($createdBy),
            new DateTimeImmutable($createdAt),
            $expires,
            ApiKey::generateApiKey(),
        );
    }

    public static function fromDatabase(array $data): self
    {
        $expiresAt = $data['expires_at'] === null ? null : new DateTimeImmutable($data['expires_at']);

        return new self(
            KeyId::fromInt($data['id']),
            KeyOrigin::fromString($data['origin']),
            UserId::fromInt($data['created_by']),
            new DateTimeImmutable($data['created_at']),
            $expiresAt,
            ApiKey::from($data['api_key']),
        );
    }

    public function isValid(): bool
    {
        if ($this->expiresAt === null) {
            return true;
        }

        return $this->expiresAt > new DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->keyId?->asInt(),
            'origin' => $this->origin->__toString(),
            'created_by' => $this->createdBy->asInt(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'api_key' => $this->apiKey->__toString(),
        ];
    }

    public function getKeyId(): ?KeyId
    {
        return $this->keyId;
    }

    public function getOrigin(): KeyOrigin
    {
        return $this->origin;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): UserId
    {
        return $this->createdBy;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getApiKey(): ApiKey
    {
        return $this->apiKey;
    }

    public function setOrigin(KeyOrigin $origin): void
    {
        $this->origin = $origin;
    }
}
