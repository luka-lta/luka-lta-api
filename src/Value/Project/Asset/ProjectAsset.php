<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project\Asset;

use DateTimeImmutable;
use LukaLtaApi\Value\Project\ProjectId;
use Ramsey\Uuid\Uuid;

class ProjectAsset
{
    private function __construct(
        private readonly string            $assetId,
        private readonly ProjectId         $projectId,
        private readonly ProjectAssetType  $type,
        private readonly string            $objectKey,
        private readonly ?string           $altText,
        private readonly int               $sortOrder,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        ProjectId        $projectId,
        ProjectAssetType $type,
        string           $objectKey,
        ?string          $altText = null,
        int              $sortOrder = 0,
    ): self {
        return new self(
            Uuid::uuid4()->toString(),
            $projectId,
            $type,
            $objectKey,
            $altText,
            $sortOrder,
            new DateTimeImmutable(),
        );
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            $row['asset_id'],
            ProjectId::fromString($row['project_id']),
            ProjectAssetType::fromString($row['type']),
            $row['object_key'],
            $row['alt_text'],
            (int) $row['sort_order'],
            new DateTimeImmutable($row['created_at']),
        );
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getProjectId(): ProjectId
    {
        return $this->projectId;
    }

    public function getType(): ProjectAssetType
    {
        return $this->type;
    }

    public function getObjectKey(): string
    {
        return $this->objectKey;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Die oeffentliche URL zeigt auf die eigene Proxy-Route, nicht auf MinIO —
     * Endpoint und Credentials des Object Storage bleiben damit serverseitig.
     */
    public function toArray(string $assetBaseUrl): array
    {
        return [
            'id'        => $this->assetId,
            'type'      => $this->type->value,
            'url'       => sprintf('%s/%s/assets/%s', $assetBaseUrl, $this->projectId->asString(), $this->assetId),
            'alt'       => $this->altText,
            'sortOrder' => $this->sortOrder,
        ];
    }

    public function toDatabaseRow(): array
    {
        return [
            'asset_id'   => $this->assetId,
            'project_id' => $this->projectId->asString(),
            'type'       => $this->type->value,
            'object_key' => $this->objectKey,
            'alt_text'   => $this->altText,
            'sort_order' => $this->sortOrder,
        ];
    }
}
