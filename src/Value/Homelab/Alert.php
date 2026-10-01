<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use DateTimeImmutable;
use JsonSerializable;
use Ramsey\Uuid\Uuid;

class Alert implements JsonSerializable
{
    private function __construct(
        private readonly string             $alertId,
        private readonly string             $severity,
        private readonly string             $title,
        private readonly string             $description,
        private readonly DateTimeImmutable   $createdAt,
        private readonly ?string            $containerId,
        private readonly ?string            $hostId,
    ) {
    }

    public static function create(
        string  $severity,
        string  $title,
        string  $description,
        ?string $containerId = null,
        ?string $hostId = null,
    ): self {
        return new self(
            Uuid::uuid4()->toString(),
            $severity,
            $title,
            $description,
            new DateTimeImmutable(),
            $containerId,
            $hostId,
        );
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            $row['alert_id'],
            $row['severity'],
            $row['title'],
            $row['description'],
            new DateTimeImmutable($row['created_at']),
            $row['container_id'],
            $row['host_id'],
        );
    }

    public function getAlertId(): string
    {
        return $this->alertId;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getContainerId(): ?string
    {
        return $this->containerId;
    }

    public function getHostId(): ?string
    {
        return $this->hostId;
    }

    public function jsonSerialize(): array
    {
        return [
            'id'          => $this->alertId,
            'severity'    => $this->severity,
            'title'       => $this->title,
            'description' => $this->description,
            'timestamp'   => $this->createdAt->format('Y-m-d H:i:s'),
            'containerId' => $this->containerId,
            'hostId'      => $this->hostId,
        ];
    }
}
