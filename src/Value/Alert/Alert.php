<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Alert;

use DateTimeImmutable;
use JsonSerializable;
use Ramsey\Uuid\Uuid;

class Alert implements JsonSerializable
{
    private function __construct(
        private readonly string            $alertId,
        private readonly string            $fingerprint,
        private readonly string            $source,
        private readonly ?string           $sourceId,
        private readonly string            $type,
        private readonly string            $severity,
        private readonly string            $title,
        private readonly string            $description,
        private readonly array             $context,
        private readonly int               $occurrenceCount,
        private readonly DateTimeImmutable $firstOccurredAt,
        private readonly DateTimeImmutable $lastOccurredAt,
        private readonly ?DateTimeImmutable $resolvedAt,
    ) {
    }

    public static function create(
        string  $fingerprint,
        string  $source,
        ?string $sourceId,
        string  $type,
        string  $severity,
        string  $title,
        string  $description,
        array   $context = [],
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            Uuid::uuid4()->toString(),
            $fingerprint,
            $source,
            $sourceId,
            $type,
            $severity,
            $title,
            $description,
            $context,
            1,
            $now,
            $now,
            null,
        );
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            $row['alert_id'],
            $row['fingerprint'],
            $row['source'],
            $row['source_id'],
            $row['type'],
            $row['severity'],
            $row['title'],
            $row['description'],
            $row['context'] !== null ? json_decode($row['context'], true, flags: JSON_THROW_ON_ERROR) : [],
            (int) $row['occurrence_count'],
            new DateTimeImmutable($row['first_occurred_at']),
            new DateTimeImmutable($row['last_occurred_at']),
            $row['resolved_at'] !== null ? new DateTimeImmutable($row['resolved_at']) : null,
        );
    }

    public function getAlertId(): string
    {
        return $this->alertId;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getSourceId(): ?string
    {
        return $this->sourceId;
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

    public function getContext(): array
    {
        return $this->context;
    }

    public function getOccurrenceCount(): int
    {
        return $this->occurrenceCount;
    }

    public function getFirstOccurredAt(): DateTimeImmutable
    {
        return $this->firstOccurredAt;
    }

    public function getLastOccurredAt(): DateTimeImmutable
    {
        return $this->lastOccurredAt;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function isResolved(): bool
    {
        return $this->resolvedAt !== null;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->alertId,
            'source' => $this->source,
            'sourceId' => $this->sourceId,
            'type' => $this->type,
            'severity' => $this->severity,
            'title' => $this->title,
            'description' => $this->description,
            'context' => (object) $this->context,
            'occurrenceCount' => $this->occurrenceCount,
            'firstOccurredAt' => $this->firstOccurredAt->format('Y-m-d H:i:s'),
            'lastOccurredAt' => $this->lastOccurredAt->format('Y-m-d H:i:s'),
            'resolvedAt' => $this->resolvedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
