<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use DateTimeImmutable;
use JsonSerializable;
use Ramsey\Uuid\Uuid;

class TopologyEdge implements JsonSerializable
{
    private function __construct(
        private readonly string            $edgeId,
        private readonly string            $sourceType,
        private readonly string            $sourceId,
        private readonly string            $targetType,
        private readonly string            $targetId,
        private readonly string            $relation,
        private readonly array             $metadata,
        private readonly bool              $manual,
    ) {
    }

    public static function create(
        string $sourceType,
        string $sourceId,
        string $targetType,
        string $targetId,
        string $relation,
        array $metadata = [],
    ): self {
        return new self(
            Uuid::uuid4()->toString(),
            $sourceType,
            $sourceId,
            $targetType,
            $targetId,
            $relation,
            $metadata,
            true,
        );
    }

    public static function computed(
        string $sourceType,
        string $sourceId,
        string $targetType,
        string $targetId,
        string $relation,
        array $metadata = [],
    ): self {
        return new self(
            "{$sourceType}:{$sourceId}->{$relation}->{$targetType}:{$targetId}",
            $sourceType,
            $sourceId,
            $targetType,
            $targetId,
            $relation,
            $metadata,
            false,
        );
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            $row['edge_id'],
            $row['source_type'],
            $row['source_id'],
            $row['target_type'],
            $row['target_id'],
            $row['relation'],
            $row['metadata'] !== null ? json_decode($row['metadata'], true) : [],
            true,
        );
    }

    public function getEdgeId(): string
    {
        return $this->edgeId;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function getTargetId(): string
    {
        return $this->targetId;
    }

    public function getRelation(): string
    {
        return $this->relation;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function jsonSerialize(): array
    {
        return [
            'id'         => $this->edgeId,
            'sourceType' => $this->sourceType,
            'sourceId'   => $this->sourceId,
            'targetType' => $this->targetType,
            'targetId'   => $this->targetId,
            'relation'   => $this->relation,
            'metadata'   => (object) $this->metadata,
            'manual'     => $this->manual,
        ];
    }
}
