<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use JsonSerializable;
use LukaLtaApi\Exception\ApiValidationException;
use Ramsey\Uuid\Uuid;

class TopologyEdge implements JsonSerializable
{
    public const array VALID_RELATIONS = ['routes_to', 'depends_on', 'connects_to', 'hosted_on', 'exposes'];

    /** Obergrenze für frei gepflegte Metadaten in Bytes (JSON-kodiert) */
    private const int MAX_METADATA_BYTES = 4096;

    private function __construct(
        private readonly string $edgeId,
        private readonly string $sourceNodeId,
        private readonly string $targetNodeId,
        private readonly string $relation,
        private readonly array  $metadata,
    ) {
    }

    public static function create(
        string $sourceNodeId,
        string $targetNodeId,
        string $relation,
        array $metadata = [],
    ): self {
        if ($sourceNodeId === $targetNodeId) {
            throw new ApiValidationException('Edge cannot connect a node to itself', 400);
        }

        if (!in_array($relation, self::VALID_RELATIONS, true)) {
            throw new ApiValidationException('Invalid edge relation', 400);
        }

        if (strlen(json_encode($metadata, JSON_THROW_ON_ERROR)) > self::MAX_METADATA_BYTES) {
            throw new ApiValidationException('Edge metadata is too large', 400);
        }

        return new self(Uuid::uuid4()->toString(), $sourceNodeId, $targetNodeId, $relation, $metadata);
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            $row['edge_id'],
            $row['source_node_id'],
            $row['target_node_id'],
            $row['relation'],
            $row['metadata'] !== null ? json_decode($row['metadata'], true) : [],
        );
    }

    public function getEdgeId(): string
    {
        return $this->edgeId;
    }

    public function getSourceNodeId(): string
    {
        return $this->sourceNodeId;
    }

    public function getTargetNodeId(): string
    {
        return $this->targetNodeId;
    }

    public function getRelation(): string
    {
        return $this->relation;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function connects(string $sourceNodeId, string $targetNodeId, string $relation): bool
    {
        return $this->sourceNodeId === $sourceNodeId
            && $this->targetNodeId === $targetNodeId
            && $this->relation === $relation;
    }

    public function jsonSerialize(): array
    {
        return [
            'id'       => $this->edgeId,
            'sourceId' => $this->sourceNodeId,
            'targetId' => $this->targetNodeId,
            'relation' => $this->relation,
            'metadata' => (object) $this->metadata,
        ];
    }
}
