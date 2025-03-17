<?php

namespace LukaLtaApi\Repository;

use Latitude\QueryBuilder\QueryFactory;
use LukaLtaApi\Api\LinkCollection\Value\LinkTreeExtraFilter;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Service\LinkItemCachingService;
use LukaLtaApi\Value\LinkCollection\LinkId;
use LukaLtaApi\Value\LinkCollection\LinkItem;
use LukaLtaApi\Value\LinkCollection\LinkItems;
use PDO;
use PDOException;

class LinkCollectionRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly LinkItemCachingService $caching,
        private readonly QueryFactory $queryFactory,
    ) {
    }

    public function create(LinkItem $link): LinkItem
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
            $link->setLinkId(LinkId::fromInt((int)$this->pdo->lastInsertId()));
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to create new link',
                previous: $exception
            );
        }

        return $link;
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

    public function update(LinkItem $linkItem): LinkItem
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

        return $linkItem;
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

    public function getAll(LinkTreeExtraFilter $filter): LinkItems
    {
        $select = $this->queryFactory->select('*')->from('link_collection');

        $query = $filter->createSqlFilter($select);
        $sql = $query->compile();

        try {
            $statement = $this->pdo->prepare($sql->sql());
            $statement->execute($sql->params());

            $linkItems = [];
            foreach ($statement as $row) {
                $linkItems[] = LinkItem::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to fetch links',
                previous: $exception
            );
        }

        return LinkItems::from(...$linkItems);
    }
}
