<?php

namespace LukaLtaApi\Repository;

use Latitude\QueryBuilder\QueryFactory;
use LukaLtaApi\Api\LinkCollection\Value\LinkTreeExtraFilter;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Service\LinkItemCachingService;
use LukaLtaApi\Value\LinkCollection\LinkId;
use LukaLtaApi\Value\LinkCollection\LinkItem;
use LukaLtaApi\Value\LinkCollection\LinkItems;
use LukaLtaApi\Value\Tracking\ClickTag;
use PDO;
use PDOException;

use function Latitude\QueryBuilder\field;

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
                (click_tag, displayname, description, url, is_active, icon_name, display_order)
            VALUES 
                (:click_tag, :displayname, :description, :url, :is_active, :icon_name, :display_order)
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'click_tag' => $link->getClickTag()->asString(),
                'displayname' => (string)$link->getMetaData()->getDisplayName(),
                'description' => $link->getMetaData()->getDescription()?->asString(),
                'url' => (string)$link->getMetaData()->getLinkUrl(),
                'is_active' => $link->getMetaData()->isActive() ? 1 : 0,
                'icon_name' => $link->getIconName()?->asString(),
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

    public function getByClickTag(ClickTag $tag): ?LinkItem
    {
        $sql = <<<SQL
            SELECT * FROM link_collection
            WHERE click_tag = :clickTag
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['clickTag' => $tag->asString()]);
            $row = $stmt->fetch();

            if ($row === false) {
                return null;
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to fetch link by clickTag',
                previous: $exception
            );
        }

        return LinkItem::fromDatabase($row);
    }

    public function findById(LinkId $linkId, bool $isAuthenticated = false): ?LinkItem
    {
        $cachedItem = $this->caching->getItem($linkId);

        if ($cachedItem !== null && $isAuthenticated) {
            return $cachedItem;
        }

        if ($cachedItem !== null && $cachedItem->getMetaData()->isActive() && !$cachedItem->isDeactivated()) {
            return $cachedItem;
        }

        if ($cachedItem !== null) {
            return null;
        }

        $sql = <<<SQL
            SELECT * FROM link_collection
            WHERE link_id = :linkId
        SQL;

        if (!$isAuthenticated) {
            $sql .= ' AND is_active = 1 AND deactivated = 0';
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['linkId' => $linkId->asInt()]);
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
                'description' => $linkItem->getMetaData()->getDescription()?->asString(),
                'url' => (string)$linkItem->getMetaData()->getLinkUrl(),
                'is_active' => $linkItem->getMetaData()->isActive() ? 1 : 0,
                'icon_name' => $linkItem->getIconName()?->asString(),
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

    public function setDeactivated(LinkId $linkId, bool $deactivated): void
    {
        if ($linkItem = $this->caching->getItem($linkId)) {
            $linkItem->setDeactivated($deactivated);
            $this->caching->updateItem($linkItem);
        }

        $sql = <<<SQL
            UPDATE link_collection
            SET deactivated = :deactivated,
                deactivated_at = :deactivated_at
            WHERE link_id = :link_id
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'link_id' => $linkId->asInt(),
                'deactivated' => $deactivated ? 1 : 0,
                'deactivated_at' => $deactivated ? date('Y-m-d H:i:s') : null,
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to update link deactivation state',
                previous: $exception,
            );
        }
    }

    public function delete(LinkId $linkId): void
    {
        $sql = 'DELETE FROM link_collection WHERE link_id = :link_id';

        try {
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare($sql);
            $statement->execute(['link_id' => $linkId->asInt()]);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new ApiDatabaseException(
                'Failed to delete link',
                previous: $exception,
            );
        }

        $this->caching->deleteItem($linkId);
    }

    public function getAll(LinkTreeExtraFilter $filter, bool $isAuthenticated = false): LinkItems
    {
        $select = $this->queryFactory->select('*')->from('link_collection');

        if (!$isAuthenticated) {
            $select->andWhere(field('is_active')->eq(1))->andWhere(field('deactivated')->eq(0));
        }

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
