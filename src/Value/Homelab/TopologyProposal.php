<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use DateTimeImmutable;
use JsonSerializable;
use LukaLtaApi\Exception\ApiValidationException;
use Ramsey\Uuid\Uuid;

class TopologyProposal implements JsonSerializable
{
    public const string STATUS_DRAFT      = 'draft';
    public const string STATUS_APPLIED    = 'applied';
    public const string STATUS_REJECTED   = 'rejected';
    public const string STATUS_SUPERSEDED = 'superseded';

    /** Spaltenbreite von `title` */
    private const int MAX_TITLE_LENGTH = 150;

    /** Obergrenze für die Zusammenfassung in Zeichen */
    private const int MAX_SUMMARY_LENGTH = 5000;

    /** Obergrenze für Items pro Proposal, damit ein Review überschaubar bleibt */
    public const int MAX_ITEMS = 200;

    /**
     * @param TopologyProposalItem[] $items
     */
    private function __construct(
        private readonly string             $proposalId,
        private readonly string             $title,
        private readonly ?string            $summary,
        private readonly string             $status,
        private readonly string             $createdBy,
        private readonly DateTimeImmutable  $createdAt,
        private readonly ?DateTimeImmutable $reviewedAt,
        private readonly array              $items,
    ) {
    }

    /**
     * @param TopologyProposalItem[] $items
     */
    public static function create(string $title, ?string $summary, string $createdBy, array $items): self
    {
        $title = trim($title);
        if ($title === '') {
            throw new ApiValidationException('Proposal title is required', 400);
        }

        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new ApiValidationException('Proposal title is too long', 400);
        }

        if ($summary !== null && mb_strlen($summary) > self::MAX_SUMMARY_LENGTH) {
            throw new ApiValidationException('Proposal summary is too long', 400);
        }

        if ($items === []) {
            throw new ApiValidationException('Proposal needs at least one item', 400);
        }

        if (count($items) > self::MAX_ITEMS) {
            throw new ApiValidationException('Proposal has too many items', 400);
        }

        return new self(
            Uuid::uuid4()->toString(),
            $title,
            $summary,
            self::STATUS_DRAFT,
            $createdBy,
            new DateTimeImmutable(),
            null,
            array_values($items),
        );
    }

    /**
     * @param TopologyProposalItem[] $items
     */
    public static function fromDatabase(array $row, array $items): self
    {
        return new self(
            $row['proposal_id'],
            $row['title'],
            $row['summary'],
            $row['status'],
            $row['created_by'],
            new DateTimeImmutable($row['created_at']),
            $row['reviewed_at'] !== null ? new DateTimeImmutable($row['reviewed_at']) : null,
            $items,
        );
    }

    public function getProposalId(): string
    {
        return $this->proposalId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    /** @return TopologyProposalItem[] */
    public function getItems(): array
    {
        return $this->items;
    }

    public function jsonSerialize(): array
    {
        return [
            'id'         => $this->proposalId,
            'title'      => $this->title,
            'summary'    => $this->summary,
            'status'     => $this->status,
            'createdBy'  => $this->createdBy,
            'createdAt'  => $this->createdAt->format('Y-m-d H:i:s'),
            'reviewedAt' => $this->reviewedAt?->format('Y-m-d H:i:s'),
            'items'      => $this->items,
        ];
    }
}
