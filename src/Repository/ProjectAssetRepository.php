<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Project\Asset\ProjectAsset;
use LukaLtaApi\Value\Project\Asset\ProjectAssets;
use LukaLtaApi\Value\Project\ProjectId;
use PDO;
use PDOException;

class ProjectAssetRepository
{
    private const string ASSET_SELECT = <<<SQL
        SELECT
            a.asset_id,
            a.project_id,
            a.type,
            a.object_key,
            a.alt_text,
            a.sort_order,
            a.created_at
        FROM project_assets a
    SQL;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getByProject(ProjectId $projectId): ProjectAssets
    {
        $sql = self::ASSET_SELECT . ' WHERE a.project_id = :project_id ORDER BY a.type ASC, a.sort_order ASC';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['project_id' => $projectId->asString()]);

            $assets = [];
            foreach ($stmt as $row) {
                $assets[] = ProjectAsset::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch project assets.', previous: $exception);
        }

        return ProjectAssets::from(...$assets);
    }

    public function getById(string $assetId): ?ProjectAsset
    {
        $sql = self::ASSET_SELECT . ' WHERE a.asset_id = :asset_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['asset_id' => $assetId]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch project asset.', previous: $exception);
        }

        return $row !== false ? ProjectAsset::fromDatabase($row) : null;
    }

    public function create(ProjectAsset $asset): ProjectAsset
    {
        $sql = <<<SQL
            INSERT INTO project_assets (asset_id, project_id, type, object_key, alt_text, sort_order)
            VALUES (:asset_id, :project_id, :type, :object_key, :alt_text, :sort_order)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($asset->toDatabaseRow());
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to create project asset.', previous: $exception);
        }

        return $asset;
    }

    public function delete(string $assetId): void
    {
        $sql = 'DELETE FROM project_assets WHERE asset_id = :asset_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['asset_id' => $assetId]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to delete project asset.', previous: $exception);
        }
    }

    public function getNextSortOrder(ProjectId $projectId): int
    {
        $sql = <<<SQL
            SELECT COALESCE(MAX(a.sort_order), -1) + 1 AS next_order
            FROM project_assets a
            WHERE a.project_id = :project_id AND a.type = 'screenshot'
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['project_id' => $projectId->asString()]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to determine next asset sort order.', previous: $exception);
        }

        return (int) $row['next_order'];
    }
}
