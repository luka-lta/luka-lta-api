<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use DateTimeImmutable;
use JsonSerializable;

class Event implements JsonSerializable
{
    private function __construct(
        private readonly ?int               $eventId,
        private readonly string             $type,
        private readonly string             $severity,
        private readonly string             $title,
        private readonly string             $description,
        private readonly ?string            $containerId,
        private readonly ?string            $hostId,
        private readonly array              $metadata,
        private readonly DateTimeImmutable  $occurredAt,
    ) {
    }

    public static function create(
        string  $type,
        string  $severity,
        string  $title,
        string  $description,
        ?string $containerId = null,
        ?string $hostId = null,
        array   $metadata = [],
    ): self {
        return new self(
            null,
            $type,
            $severity,
            $title,
            $description,
            $containerId,
            $hostId,
            $metadata,
            new DateTimeImmutable(),
        );
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            (int) $row['event_id'],
            $row['type'],
            $row['severity'],
            $row['title'],
            $row['description'],
            $row['container_id'],
            $row['host_id'],
            $row['metadata'] !== null ? json_decode($row['metadata'], true, flags: JSON_THROW_ON_ERROR) : [],
            new DateTimeImmutable($row['occurred_at']),
        );
    }

    public function getType(): string
    {
        return $this->type;
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

    public function getContainerId(): ?string
    {
        return $this->containerId;
    }

    public function getHostId(): ?string
    {
        return $this->hostId;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function jsonSerialize(): array
    {
        return [
            'id'          => $this->eventId,
            'type'        => $this->type,
            'severity'    => $this->severity,
            'title'       => $this->title,
            'description' => $this->description,
            'containerId' => $this->containerId,
            'hostId'      => $this->hostId,
            'metadata'    => (object) $this->metadata,
            'occurredAt'  => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}
