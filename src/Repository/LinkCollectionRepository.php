<?php

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\LinkCollection\LinkItem;
use PDO;
use PDOException;

class LinkCollectionRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function createNewLink(LinkItem $link): void
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
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to create new link',
                previous: $exception
            );
        }
    }

    public function getAllLinks(): ?array
    {
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
