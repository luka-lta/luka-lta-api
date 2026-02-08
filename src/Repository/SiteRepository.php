<?php

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\WebTracking\Config\SiteConfig;
use LukaLtaApi\Value\WebTracking\Site\Site;
use PDO;
use PDOException;

class SiteRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getSite(int $siteId): ?Site
    {
        $sql = <<<SQL
            SELECT * FROM sites WHERE site_id = :siteId
        SQL;


        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'siteId' => $siteId,
            ]);

            $site = $stmt->fetch();

            if (!$site) {
                return null;
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to load site',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }

        return Site::fromDatabase($site);
    }

    public function updateSite(
        int $siteId,
        array $updateData,
    ): void {
        $set = [];
        $params = ['siteId' => $siteId];

        foreach ($updateData as $column => $value) {
            $param = ':' . $column;
            $set[] = "$column = $param";
            $params[$column] = $value;
        }

        $set[] = 'updated_at = NOW()';

        $sql = sprintf(
            'UPDATE sites SET %s WHERE site_id = :siteId',
            implode(', ', $set)
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to update site',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }
}
