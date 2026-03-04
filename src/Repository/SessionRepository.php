<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Exception\InvalidSessionResultException;
use LukaLtaApi\QueryBuilder\Value\QueryContext;
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

    public function getSessionsFromTrackingUser(string $trackingUserId, QueryContext $queryContext): ?array
    {
        $timeStatement = $queryContext->getTimeStatement();
        $offsetQuery = $queryContext->getOffsetStatement();
        $limitQuery = $queryContext->getLimitStatement();

        $sql = <<<SQL
            WITH AggregatedSessions AS (
                SELECT
                    session_id AS sessionId, 
                    -- argMax / argMin via GROUP_CONCAT trick
                    SUBSTRING_INDEX(GROUP_CONCAT(user_id             ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS userId,
                    SUBSTRING_INDEX(GROUP_CONCAT(country             ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS country,
                    SUBSTRING_INDEX(GROUP_CONCAT(region              ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS region,
                    SUBSTRING_INDEX(GROUP_CONCAT(city                ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS city,
                    SUBSTRING_INDEX(GROUP_CONCAT(language            ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS language,
                    SUBSTRING_INDEX(GROUP_CONCAT(device_type         ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS device,
                    SUBSTRING_INDEX(GROUP_CONCAT(browser             ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS browser,
                    SUBSTRING_INDEX(GROUP_CONCAT(browser_version     ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS browserVersion,
                    SUBSTRING_INDEX(GROUP_CONCAT(os    ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS os,
                    SUBSTRING_INDEX(GROUP_CONCAT(os_version ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS osVersion,
                    SUBSTRING_INDEX(GROUP_CONCAT(screen_width        ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS screenWidth,
                    SUBSTRING_INDEX(GROUP_CONCAT(screen_height       ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS screenHeight,
                    SUBSTRING_INDEX(GROUP_CONCAT(ip                  ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS ip,
                    SUBSTRING_INDEX(GROUP_CONCAT(lat                 ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS lat,
                    SUBSTRING_INDEX(GROUP_CONCAT(lon                 ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS lon,
            
                    -- argMin (ältester Wert = ORDER BY ASC)
                    SUBSTRING_INDEX(GROUP_CONCAT(referrer            ORDER BY occurred_on ASC  SEPARATOR '|||'), '|||', 1) AS referrer,
                    SUBSTRING_INDEX(GROUP_CONCAT(channel             ORDER BY occurred_on ASC  SEPARATOR '|||'), '|||', 1) AS channel,
                    SUBSTRING_INDEX(GROUP_CONCAT(hostname            ORDER BY occurred_on ASC  SEPARATOR '|||'), '|||', 1) AS hostname,
            
                    -- url_parameters als JSON-Spalte (frühester Eintrag)
                    JSON_UNQUOTE(JSON_EXTRACT(
                        SUBSTRING_INDEX(GROUP_CONCAT(url_parameters ORDER BY occurred_on ASC SEPARATOR '|||'), '|||', 1),
                        '$.utm_source'))   AS utmSource,
                    JSON_UNQUOTE(JSON_EXTRACT(
                        SUBSTRING_INDEX(GROUP_CONCAT(url_parameters ORDER BY occurred_on ASC SEPARATOR '|||'), '|||', 1),
                        '$.utm_medium'))   AS utmMedium,
                    JSON_UNQUOTE(JSON_EXTRACT(
                        SUBSTRING_INDEX(GROUP_CONCAT(url_parameters ORDER BY occurred_on ASC SEPARATOR '|||'), '|||', 1),
                        '$.utm_campaign')) AS utmCampaign,
                    JSON_UNQUOTE(JSON_EXTRACT(
                        SUBSTRING_INDEX(GROUP_CONCAT(url_parameters ORDER BY occurred_on ASC SEPARATOR '|||'), '|||', 1),
                        '$.utm_term'))     AS utmTerm,
                    JSON_UNQUOTE(JSON_EXTRACT(
                        SUBSTRING_INDEX(GROUP_CONCAT(url_parameters ORDER BY occurred_on ASC SEPARATOR '|||'), '|||', 1),
                        '$.utm_content'))  AS utmContent,
            
                    -- Zeitstempel
                    MAX(occurred_on) AS sessionEnd,
                    MIN(occurred_on) AS sessionStart,
                    TIMESTAMPDIFF(SECOND, MIN(occurred_on), MAX(occurred_on)) AS sessionDuration,
            
                    -- argMinIf / argMaxIf für entry/exit page
                    SUBSTRING_INDEX(GROUP_CONCAT(
                        CASE WHEN type = 'pageview' THEN pathname END
                        ORDER BY occurred_on ASC SEPARATOR '|||'
                    ), '|||', 1) AS entryPage,
            
                    SUBSTRING_INDEX(GROUP_CONCAT(
                        CASE WHEN type = 'pageview' THEN pathname END
                        ORDER BY occurred_on DESC SEPARATOR '|||'
                    ), '|||', 1) AS exitPage,
            
                    -- countIf
                    SUM(CASE WHEN type = 'pageview'      THEN 1 ELSE 0 END) AS pageviews,
                    SUM(CASE WHEN type = 'custom_event'  THEN 1 ELSE 0 END) AS events,
                    SUM(CASE WHEN type = 'error'         THEN 1 ELSE 0 END) AS errors,
                    SUM(CASE WHEN type = 'outbound'      THEN 1 ELSE 0 END) AS outbound,
                    SUM(CASE WHEN type = 'button_click'  THEN 1 ELSE 0 END) AS buttonClicks,
                    SUM(CASE WHEN type = 'copy'          THEN 1 ELSE 0 END) AS copies,
                    SUM(CASE WHEN type = 'form_submit'   THEN 1 ELSE 0 END) AS formSubmits,
                    SUM(CASE WHEN type = 'input_change'  THEN 1 ELSE 0 END) AS inputChanges
            
                FROM events
                WHERE
                    site_id = :siteId
                    AND events.user_id = :trackingUserId
                     $timeStatement
                GROUP BY
                    sessionId
                ORDER BY sessionEnd DESC
            )
              SELECT
                  a.*
              FROM AggregatedSessions a
              $limitQuery $offsetQuery
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'trackingUserId' => $trackingUserId,
                'siteId' => $queryContext->siteId,
            ]);

            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($result)) {
                return null;
            }

            return $result;
        } catch (PDOException $e) {
            throw new ApiDatabaseException(
                'Failed to get sessions for user',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }

    public function getSession(int $siteId, string $sessionId, int $limit, int $offset): array
    {
        $sessionQuery = <<<SQL
            SELECT
                session_id AS sessionId,
                MAX(user_id) as userId,
                MAX(country) as country,
                MAX(region) as region,
                MAX(city) as city,
                MAX(language) as language,
                MAX(device_type) as device,
                MAX(browser) as browser,
                MAX(browser_version) as browserVersion,
                MAX(os) as os,
                MAX(os_version) as osVersion,
                MAX(screen_width) as screenWidth,
                MAX(screen_height) as screenHeight,
                MAX(referrer) as referrer,
                MAX(channel) as channel,
                MIN(occurred_on) as sessionStart,
                MAX(occurred_on) as sessionEnd,
                TIMESTAMPDIFF(SECOND, MIN(occurred_on), MAX(occurred_on)) as sessionDuration,
                SUM(CASE WHEN type = 'pageview' THEN 1 ELSE 0 END) as pageviews,
                COUNT(*) as events,
                SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN type = 'pageview' THEN pathname END ORDER BY occurred_on ASC), ',', 1) as entryPage,
                SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN type = 'pageview' THEN pathname END ORDER BY occurred_on DESC), ',', 1) as exitPage,
                MAX(ip) AS ip
            FROM events
            WHERE
                site_id = :siteId
                AND session_id = :sessionId
            GROUP BY session_id
            LIMIT 1;
        SQL;

        $countQuery = <<<SQL
            SELECT
                COUNT(*) as total
            FROM events
            WHERE
                site_id = :siteId
                AND session_id = :sessionId
                AND type != 'performance'
        SQL;

        $eventsQuery = <<<SQL
            SELECT
                occurred_on AS occurredOn,
                pathname AS pathname,
                hostname AS hostname,
                url_parameters AS urlParameters,
                page_title AS pageTitle,
                referrer AS referrer,
                type AS type,
                event_name AS eventName,
                props AS props
            FROM events
            WHERE
                site_id = :siteId
                AND session_id = :sessionId
                AND type != 'performance'
            ORDER BY occurred_on ASC
            LIMIT :limit
            OFFSET :offset;
        SQL;

        try {
            $stmt = $this->pdo->prepare($sessionQuery);
            $stmt->execute([
                'siteId' => $siteId,
                'sessionId' => $sessionId,
            ]);

            $sessionResults = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $stmt = $this->pdo->prepare($eventsQuery);
            $stmt->execute([
                'siteId' => $siteId,
                'sessionId' => $sessionId,
                'limit' => $limit,
                'offset' => $offset,
            ]);

            $eventsResults = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $stmt = $this->pdo->prepare($countQuery);
            $stmt->execute([
                'siteId' => $siteId,
                'sessionId' => $sessionId,
            ]);

            $countResults = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($sessionResults) || empty($countResults)) {
                throw new InvalidSessionResultException(
                    'Failed to get sessions for session' . $sessionId,
                    StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                );
            }

            $countResults[0]['limit'] = $limit;
            $countResults[0]['offset'] = $offset;
            $countResults[0]['hasMore'] = $offset + count($eventsResults) < $countResults[0]['total'];
        } catch (PDOException $e) {
            throw new ApiDatabaseException(
                'Failed to get session information for sessionId' . $sessionId,
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e,
            );
        }

        $pagination = [
            'total'   => $countResults[0]['total'],
            'limit'   => $limit,
            'offset'  => $offset,
            'hasMore' => $offset + count($eventsResults) < $countResults[0]['total'],
        ];

        return [
            'session' => $sessionResults[0],
            'events' => $eventsResults,
            'pagination' => $pagination,
        ];
    }
}
