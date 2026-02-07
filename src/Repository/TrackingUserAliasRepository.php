<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Tracking\User\TrackingUser;
use PDO;
use PDOException;

class TrackingUserAliasRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getUserAlias(int $siteId, string $anonymousId): array|false
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

    public function insertUserAlias(TrackingUser $user): void
    {
        $sql = <<<SQL
            INSERT INTO 
                tracking_user_alias 
            SET site_id = :site_id, anonymous_id = :anonymous_id, user_id = :user_id;
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'site_id' => $user->getSiteId(),
                'anonymous_id' => $user->getAnonymousId(),
                'user_id' => $user->getUserId(),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to get tracking user alias',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }
}
