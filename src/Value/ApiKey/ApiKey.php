<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\ApiKey;

use DateTimeImmutable;
use JsonSerializable;
use LukaLtaApi\Value\User\UserId;

class ApiKey implements JsonSerializable
{
    private Permissions $permissions;

    private function __construct(
        private readonly ApiKeyId          $keyId,
        private readonly string            $label,
        private readonly string            $origin,
        private readonly string            $keySuffix,
        private readonly UserId            $createdBy,
        private readonly DateTimeImmutable $createdAt,
        private readonly ?DateTimeImmutable $expiresAt,
    ) {
        $this->permissions = Permissions::from();
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            ApiKeyId::fromInt((int) $row['key_id']),
            $row['label'],
            $row['origin'],
            $row['key_suffix'],
            UserId::fromInt((int) $row['created_by']),
            new DateTimeImmutable($row['created_at']),
            $row['expires_at'] !== null ? new DateTimeImmutable($row['expires_at']) : null,
        );
    }

    public function getKeyId(): ApiKeyId
    {
        return $this->keyId;
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    public function setPermissions(Permissions $permissions): void
    {
        $this->permissions = $permissions;
    }

    public function jsonSerialize(): array
    {
        return [
            'id'          => $this->keyId->asInt(),
            'label'       => $this->label,
            'origin'      => $this->origin,
            'keyPreview'  => "****{$this->keySuffix}",
            'permissions' => $this->permissions,
            'createdBy'   => $this->createdBy->asInt(),
            'createdAt'   => $this->createdAt->format('Y-m-d H:i:s'),
            'expiresAt'   => $this->expiresAt?->format('Y-m-d H:i:s'),
        ];
    }
}
