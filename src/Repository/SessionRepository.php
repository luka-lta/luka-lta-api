<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Tracking\TrackingSession;
use PDO;
use PDOException;

class SessionRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getExistingSession(string $userId, int $siteId): ?TrackingSession
    {
        $sql = <<<SQL
            SELECT * FROM active_sessions WHERE user_id = :userId AND site_id = :siteId
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'userId' => $userId,
                'siteId' => $siteId,
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return null;
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to get existing Sessions for userId ' . $userId . ' site ' . $siteId,
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }

        return TrackingSession::fromDatabase($result);
    }

    public function updateSession(string $userId, int $siteId): string
    {
        try {
            if ($existingSession = $this->getExistingSession($userId, $siteId)) {
                $sql = <<<SQL
                UPDATE
                    active_sessions 
                SET last_activity = NOW() 
                WHERE user_id = :userId 
                  AND site_id = :siteId
            SQL;

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    'userId' => $userId,
                    'siteId' => $siteId,
                ]);

                return $existingSession->getSessionId();
            }

            $sessionId = bin2hex(random_bytes(16));
            $sql = <<<SQL
                INSERT INTO 
                    active_sessions (session_id, user_id, site_id, last_activity)
                VALUES (:sessionId, :userId, :siteId, NOW())
            SQL;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'userId' => $userId,
                'siteId' => $siteId,
                'sessionId' => $sessionId,
            ]);

            return $sessionId;
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to update Session for userId ' . $userId . ' site ' . $siteId,
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }

    public function cleanupSessions(): void
    {
        $sql = <<<SQL
            DELETE FROM active_sessions WHERE last_activity < (NOW() - INTERVAL 30 MINUTE)
        SQL;

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to cleanup sessions',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }
}
