<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use DateTimeImmutable;
use JsonSerializable;
use Ramsey\Uuid\Uuid;

class TopologyNode implements JsonSerializable
{
    private function __construct(
        private readonly string            $nodeId,
        private readonly string            $type,
        private readonly string            $name,
        private readonly string            $status,
        private readonly array             $metadata,
        private readonly DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(string $type, string $name, string $status, array $metadata): self
    {
        return new self(Uuid::uuid4()->toString(), $type, $name, $status, $metadata, new DateTimeImmutable());
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            $row['node_id'],
            $row['type'],
            $row['name'],
            $row['status'],
            $row['metadata'] !== null ? json_decode($row['metadata'], true) : [],
            new DateTimeImmutable($row['updated_at']),
        );
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function jsonSerialize(): array
    {
        return [
            'id'        => $this->nodeId,
            'type'      => $this->type,
            'name'      => $this->name,
            'status'    => $this->status,
            'metadata'  => (object) $this->metadata,
            'manual'    => true,
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
