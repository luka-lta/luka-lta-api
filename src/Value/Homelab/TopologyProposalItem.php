<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use JsonSerializable;
use LukaLtaApi\Exception\ApiValidationException;
use Ramsey\Uuid\Uuid;

/**
 * Eine einzelne vorgeschlagene Änderung. Der Payload ist bereits beim Anlegen strukturell
 * geprüft (siehe TopologyProposalParser); gegen den dann aktuellen Stand wird erst beim
 * Übernehmen erneut geprüft.
 */
class TopologyProposalItem implements JsonSerializable
{
    public const string OPERATION_ADD_NODE    = 'add_node';
    public const string OPERATION_UPDATE_NODE = 'update_node';
    public const string OPERATION_REMOVE_NODE = 'remove_node';
    public const string OPERATION_ADD_EDGE    = 'add_edge';
    public const string OPERATION_REMOVE_EDGE = 'remove_edge';

    public const array OPERATIONS = [
        self::OPERATION_ADD_NODE,
        self::OPERATION_UPDATE_NODE,
        self::OPERATION_REMOVE_NODE,
        self::OPERATION_ADD_EDGE,
        self::OPERATION_REMOVE_EDGE,
    ];

    public const string STATUS_PENDING  = 'pending';
    public const string STATUS_APPLIED  = 'applied';
    public const string STATUS_REJECTED = 'rejected';
    public const string STATUS_FAILED   = 'failed';

    /** Obergrenze für die Begründung eines Items in Zeichen */
    private const int MAX_REASONING_LENGTH = 2000;

    /** Obergrenze für Belege eines Items in Bytes (JSON-kodiert) */
    private const int MAX_EVIDENCE_BYTES = 4096;

    /** Spaltenbreite von `failure` */
    private const int MAX_FAILURE_LENGTH = 255;

    private function __construct(
        private readonly string  $itemId,
        private readonly int     $position,
        private readonly string  $operation,
        private readonly array   $payload,
        private readonly string  $reasoning,
        private readonly array   $evidence,
        private readonly string  $status,
        private readonly ?string $failure,
    ) {
    }

    public static function create(
        int $position,
        string $operation,
        array $payload,
        string $reasoning,
        array $evidence,
    ): self {
        if (!in_array($operation, self::OPERATIONS, true)) {
            throw new ApiValidationException('Invalid proposal operation', 400);
        }

        $reasoning = trim($reasoning);
        if ($reasoning === '') {
            throw new ApiValidationException('Reasoning is required', 400);
        }

        if (mb_strlen($reasoning) > self::MAX_REASONING_LENGTH) {
            throw new ApiValidationException('Reasoning is too long', 400);
        }

        foreach ($evidence as $entry) {
            if (!is_string($entry)) {
                throw new ApiValidationException('Evidence must be a list of strings', 400);
            }
        }

        if (strlen(json_encode($evidence, JSON_THROW_ON_ERROR)) > self::MAX_EVIDENCE_BYTES) {
            throw new ApiValidationException('Evidence is too large', 400);
        }

        return new self(
            Uuid::uuid4()->toString(),
            $position,
            $operation,
            $payload,
            $reasoning,
            array_values($evidence),
            self::STATUS_PENDING,
            null,
        );
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            $row['item_id'],
            (int) $row['position'],
            $row['operation'],
            json_decode($row['payload'], true),
            $row['reasoning'],
            $row['evidence'] !== null ? json_decode($row['evidence'], true) : [],
            $row['status'],
            $row['failure'],
        );
    }

    public function applied(): self
    {
        return $this->withStatus(self::STATUS_APPLIED, null);
    }

    public function rejected(): self
    {
        return $this->withStatus(self::STATUS_REJECTED, null);
    }

    public function failed(string $failure): self
    {
        return $this->withStatus(self::STATUS_FAILED, mb_substr($failure, 0, self::MAX_FAILURE_LENGTH));
    }

    private function withStatus(string $status, ?string $failure): self
    {
        return new self(
            $this->itemId,
            $this->position,
            $this->operation,
            $this->payload,
            $this->reasoning,
            $this->evidence,
            $status,
            $failure,
        );
    }

    public function getItemId(): string
    {
        return $this->itemId;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getReasoning(): string
    {
        return $this->reasoning;
    }

    public function getEvidence(): array
    {
        return $this->evidence;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getFailure(): ?string
    {
        return $this->failure;
    }

    public function jsonSerialize(): array
    {
        $payload = $this->payload;

        // Nach dem DB-Roundtrip ist ein leeres Objekt ein leeres Array — als Objekt ausliefern.
        if (array_key_exists('metadata', $payload)) {
            $payload['metadata'] = (object) $payload['metadata'];
        }

        return [
            'id'        => $this->itemId,
            'position'  => $this->position,
            'operation' => $this->operation,
            'payload'   => (object) $payload,
            'reasoning' => $this->reasoning,
            'evidence'  => $this->evidence,
            'status'    => $this->status,
            'failure'   => $this->failure,
        ];
    }
}
