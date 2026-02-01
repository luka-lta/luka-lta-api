<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use PDO;
use PDOException;

class TrackingUserAliasRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getUserAlias(int $siteId, string $anonymousId): ?string
    {
        $sql = <<<SQL
            SELECT *
            FROM tracking_user_alias
            WHERE anonymous_id = :anonymous_id 
            AND site_id = :site_id;
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'anonymous_id' => $anonymousId,
                'site_id' => $siteId,
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to get tracking user alias',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }

        return $result;
    }
}
