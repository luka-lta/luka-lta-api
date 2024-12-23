<?php

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Service\LinkItemCachingService;
use LukaLtaApi\Value\LinkCollection\LinkId;
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
                (displayname, description, url, is_active, icon_name, display_order)
            VALUES 
                (:displayname, :description, :url, :is_active, :icon_name, :display_order)
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'displayname' => (string)$link->getMetaData()->getDisplayName(),
                'description' => $link->getMetaData()->getDescription()?->getValue(),
                'url' => (string)$link->getMetaData()->getLinkUrl(),
                'is_active' => $link->getMetaData()->isActive() ? 1 : 0,
                'icon_name' => $link->getIconName()?->getValue(),
                'display_order' => $link->getDisplayOrder(),
            ]);

            $this->caching->addItem($link);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to create new link',
                previous: $exception
            );
        }
    }

    public function getById(LinkId $linkId): ?LinkItem
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
            $stmt->execute(['linkid' => $linkId->asInt()]);
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
                icon_name = :icon_name,
                display_order = :display_order
            WHERE link_id = :link_id
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'displayname' => (string)$linkItem->getMetaData()->getDisplayName(),
                'description' => $linkItem->getMetaData()->getDescription()?->getValue(),
                'url' => (string)$linkItem->getMetaData()->getLinkUrl(),
                'is_active' => $linkItem->getMetaData()->isActive() ? 1 : 0,
                'icon_name' => $linkItem->getIconName()?->getValue(),
                'link_id' => $linkItem->getLinkId()?->asInt(),
                'display_order' => $linkItem->getDisplayOrder(),
            ]);

            $this->caching->updateItem($linkItem);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to update link',
                previous: $exception
            );
        }
    }

    public function disableLink(LinkId $linkId): void
    {
        if ($linkItem = $this->caching->getItem($linkId)) {
            $linkItem->setDeactivated(true);
            $this->caching->updateItem($linkItem);
        }

        $sql = <<<SQL
            UPDATE link_collection
            SET deactivated = :deactivated,
                deactivated_at = NOW()
            WHERE link_id = :link_id
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'link_id' => $linkId->asInt(),
                'deactivated' => 1,
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to disable link',
                previous: $exception,
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
