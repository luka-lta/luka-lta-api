<?php

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Service\LinkItemCachingService;
use LukaLtaApi\Value\LinkCollection\LinkItem;
use PDO;
use PDOException;

class LinkCollectionRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly LinkItemCachingService $caching,
    ) {
    }

    public function create(LinkItem $link): void
    {
        $sql = <<<SQL
            INSERT INTO link_collection 
                (displayname, description, url, is_active, icon_name)
            VALUES 
                (:displayname, :description, :url, :is_active, :icon_name)
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'displayname' => (string)$link->getDisplayName(),
                'description' => $link->getDescription()?->getValue(),
                'url' => (string)$link->getUrl(),
                'is_active' => $link->isActive() ? 1 : 0,
                'icon_name' => $link->getIconName()?->getValue(),
            ]);

            $this->caching->addItem($link);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to create new link',
                previous: $exception
            );
        }
    }

    public function getById(int $linkId): ?LinkItem
    {
        if ($linkItem = $this->caching->getItem($linkId)) {
            return $linkItem;
        }

        $sql = <<<SQL
            SELECT * FROM link_collection
            WHERE link_id = :linkid
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['linkid' => $linkId]);
            $row = $stmt->fetch();

            if ($row === false) {
                return null;
            }

        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to fetch link by id',
                previous: $exception
            );
        }

        return LinkItem::fromDatabase($row);
    }

    public function update(LinkItem $linkItem): void
    {
        $sql = <<<SQL
            UPDATE link_collection
            SET displayname = :displayname,
                description = :description,
                url = :url,
                is_active = :is_active,
                icon_name = :icon_name
            WHERE link_id = :link_id
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'displayname' => (string)$linkItem->getDisplayName(),
                'description' => $linkItem->getDescription()?->getValue(),
                'url' => (string)$linkItem->getUrl(),
                'is_active' => $linkItem->isActive() ? 1 : 0,
                'icon_name' => $linkItem->getIconName()?->getValue(),
                'link_id' => $linkItem->getId(),
            ]);

            $this->caching->updateItem($linkItem);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to update link',
                previous: $exception
            );
        }
    }

    public function getAll(): ?array
    {
        if ($linkItems = $this->caching->getAllItems()) {
            return $linkItems;
        }

        $sql = <<<SQL
            SELECT * FROM link_collection
        SQL;

        try {
            $statement = $this->pdo->query($sql);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

            if ($rows === false) {
                return null;
            }

        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to fetch links',
                previous: $exception
            );
        }

        return array_map(static fn($row) => LinkItem::fromDatabase($row), $rows);
    }
}
