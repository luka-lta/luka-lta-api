<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use DateTimeImmutable;
use JsonSerializable;
use LukaLtaApi\Exception\ApiValidationException;
use Ramsey\Uuid\Uuid;

class TopologyNode implements JsonSerializable
{
    public const array VALID_STATUSES = ['healthy', 'warning', 'offline', 'unknown'];

    /** Freier Typ (z. B. `reverse-proxy`, `database`), Breite der Spalte `type` */
    private const string TYPE_PATTERN = '/^[a-z0-9][a-z0-9-]{0,49}$/';

    /** Breite der Spalte `name` */
    private const int MAX_NAME_LENGTH = 100;

    /** Obergrenze für frei gepflegte Metadaten in Bytes (JSON-kodiert) */
    private const int MAX_METADATA_BYTES = 4096;

    private function __construct(
        private readonly string            $nodeId,
        private readonly string            $type,
        private readonly string            $name,
        private readonly string            $status,
        private readonly NodeReference     $reference,
        private readonly array             $metadata,
        private readonly DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        string $type,
        string $name,
        string $status,
        NodeReference $reference,
        array $metadata,
    ): self {
        return new self(
            Uuid::uuid4()->toString(),
            self::parseType($type),
            self::parseName($name),
            self::parseStatus($status),
            $reference,
            self::parseMetadata($metadata),
            new DateTimeImmutable(),
        );
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            $row['node_id'],
            $row['type'],
            $row['name'],
            $row['status'],
            NodeReference::fromDatabase($row['ref_type'], $row['ref_host_id'], $row['ref_service_key']),
            $row['metadata'] !== null ? json_decode($row['metadata'], true) : [],
            new DateTimeImmutable($row['updated_at']),
        );
    }

    /**
     * Übernimmt nur die gesetzten Felder; der Verweis ist unveränderlich (neuer Knoten statt Umhängen).
     */
    public function withChanges(?string $type, ?string $name, ?string $status, ?array $metadata): self
    {
        return new self(
            $this->nodeId,
            $type !== null ? self::parseType($type) : $this->type,
            $name !== null ? self::parseName($name) : $this->name,
            $status !== null ? self::parseStatus($status) : $this->status,
            $this->reference,
            $metadata !== null ? self::parseMetadata($metadata) : $this->metadata,
            new DateTimeImmutable(),
        );
    }

    private static function parseType(string $type): string
    {
        $type = strtolower(trim($type));
        if (preg_match(self::TYPE_PATTERN, $type) !== 1) {
            throw new ApiValidationException('Invalid node type', 400);
        }

        return $type;
    }

    private static function parseName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new ApiValidationException('Node name cannot be empty', 400);
        }

        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new ApiValidationException('Node name is too long', 400);
        }

        return $name;
    }

    private static function parseStatus(string $status): string
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new ApiValidationException('Invalid node status', 400);
        }

        return $status;
    }

    private static function parseMetadata(array $metadata): array
    {
        if (strlen(json_encode($metadata, JSON_THROW_ON_ERROR)) > self::MAX_METADATA_BYTES) {
            throw new ApiValidationException('Node metadata is too large', 400);
        }

        return $metadata;
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

    public function getReference(): NodeReference
    {
        return $this->reference;
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
            'ref'       => $this->reference,
            'metadata'  => (object) $this->metadata,
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
