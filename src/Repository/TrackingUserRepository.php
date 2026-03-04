<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use PDO;
use PDOException;

class TrackingUserRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getAllTrackingUsers(int $siteId, int $limit, int $offset): ?array
    {
        $sql = <<<SQL
            WITH AggregatedUsers AS (
                SELECT
                    -- Group by effective user: identified_user_id for identified users, user_id (device) for anonymous
                    SUBSTRING_INDEX(GROUP_CONCAT(user_id ORDER BY occurred_on DESC), ',', 1) AS userId,
                    SUBSTRING_INDEX(GROUP_CONCAT(country ORDER BY occurred_on DESC), ',', 1) AS country,
                    SUBSTRING_INDEX(GROUP_CONCAT(region ORDER BY occurred_on DESC), ',', 1) AS region,
                    SUBSTRING_INDEX(GROUP_CONCAT(city ORDER BY occurred_on DESC), ',', 1) AS city,
                    SUBSTRING_INDEX(GROUP_CONCAT(language ORDER BY occurred_on DESC), ',', 1) AS language,
                    SUBSTRING_INDEX(GROUP_CONCAT(browser ORDER BY occurred_on DESC), ',', 1) AS browser,
                    SUBSTRING_INDEX(GROUP_CONCAT(browser_version ORDER BY occurred_on DESC), ',', 1) AS browserVersion,
                    SUBSTRING_INDEX(GROUP_CONCAT(os ORDER BY occurred_on DESC), ',', 1) AS os,
                    SUBSTRING_INDEX(GROUP_CONCAT(os_version ORDER BY occurred_on DESC), ',', 1) AS osVersion,
                    SUBSTRING_INDEX(GROUP_CONCAT(device_type ORDER BY occurred_on DESC), ',', 1) AS device,
                    SUBSTRING_INDEX(GROUP_CONCAT(screen_width ORDER BY occurred_on DESC), ',', 1) AS screenWidth,
                    SUBSTRING_INDEX(GROUP_CONCAT(screen_height ORDER BY occurred_on DESC), ',', 1) AS screenHeight,
                    SUBSTRING_INDEX(GROUP_CONCAT(referrer ORDER BY occurred_on ASC), ',', 1) AS referrer,
                    SUBSTRING_INDEX(GROUP_CONCAT(channel ORDER BY occurred_on DESC), ',', 1) AS channel,
                    SUBSTRING_INDEX(GROUP_CONCAT(hostname ORDER BY occurred_on ASC), ',', 1) AS hostname,
                    SUM(type = 'pageview') AS pageviews,
                    SUM(type = 'custom_event') AS events,
                    COUNT(DISTINCT session_id) AS sessions,
                    MAX(occurred_on) AS lastSeen,
                    MIN(occurred_on) AS firstSeen
                FROM events
                WHERE
                    site_id = :siteId
                GROUP BY
                    user_id
            )
            SELECT
                *
            FROM AggregatedUsers
            LIMIT :limit OFFSET :offset
        SQL;


        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':siteId' => $siteId,
                ':limit' => $limit,
                ':offset' => $offset,
            ]);

            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($result)) {
                return null;
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to get tracking users',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }

        return [
            'results' => $result,
            'count' => count($result),
        ];
    }

    public function getTrackingUser(int $siteId, string $userId): ?array
    {
        $sql = <<<SQL
            WITH sessions AS (
                SELECT
                    session_id,
                    -- argMax equivalent: get value corresponding to max timestamp
                    SUBSTRING_INDEX(GROUP_CONCAT(user_id ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS userId,
                    SUBSTRING_INDEX(GROUP_CONCAT(country ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS country,
                    SUBSTRING_INDEX(GROUP_CONCAT(region ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS region,
                    SUBSTRING_INDEX(GROUP_CONCAT(city ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS city,
                    SUBSTRING_INDEX(GROUP_CONCAT(language ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS language,
                    SUBSTRING_INDEX(GROUP_CONCAT(device_type ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS device,
                    SUBSTRING_INDEX(GROUP_CONCAT(browser ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS browser,
                    SUBSTRING_INDEX(GROUP_CONCAT(browser_version ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS browserVersion,
                    SUBSTRING_INDEX(GROUP_CONCAT(os ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS os,
                    SUBSTRING_INDEX(GROUP_CONCAT(os_version ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS osVersion,
                    SUBSTRING_INDEX(GROUP_CONCAT(screen_width ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS screenWidth,
                    SUBSTRING_INDEX(GROUP_CONCAT(screen_height ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS screenHeight,
                    -- argMin equivalent: get value corresponding to min timestamp
                    SUBSTRING_INDEX(GROUP_CONCAT(referrer ORDER BY occurred_on ASC SEPARATOR '|||'), '|||', 1) AS referrer,
                    MAX(occurred_on) AS sessionEnd,
                    MIN(occurred_on) AS sessionStart,
                    TIMESTAMPDIFF(SECOND, MIN(occurred_on), MAX(occurred_on)) AS session_duration,
                    -- argMinIf / argMaxIf equivalent
                    SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN type = 'pageview' THEN pathname END ORDER BY occurred_on ASC SEPARATOR '|||'), '|||', 1) AS entryPage,
                    SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN type = 'pageview' THEN pathname END ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS exitPage,
                    SUM(CASE WHEN type = 'pageview' THEN 1 ELSE 0 END) AS pageviews,
                    SUM(CASE WHEN type = 'custom_event' THEN 1 ELSE 0 END) AS events,
                    SUBSTRING_INDEX(GROUP_CONCAT(ip ORDER BY occurred_on DESC SEPARATOR '|||'), '|||', 1) AS ip
                FROM
                    events
                WHERE
                    (events.user_id = :userId)
                    AND site_id = :site
                GROUP BY
                    session_id
                ORDER BY
                    sessionEnd DESC
            )
            SELECT
                COUNT(DISTINCT session_id) AS sessions,
                ROUND(AVG(session_duration)) AS duration,
                MIN(userId) AS userId,
                MIN(country) AS country,
                MIN(region) AS region,
                MIN(city) AS city,
                MIN(language) AS language,
                MIN(device) AS device,
                MIN(browser) AS browser,
                MIN(browserVersion) AS browserVersion,
                MIN(os) AS os,
                MIN(osVersion) AS osVersion,
                MIN(screenHeight) AS screenHeight,
                MIN(screenWidth) AS screenWidth,
                MAX(sessionEnd) AS lastSeen,
                MIN(sessionStart) AS firstSeen,
                SUM(pageviews) AS pageviews,
                SUM(events) AS events,
                MIN(ip) AS ip
            FROM
                sessions;
        SQL;


        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'userId' => $userId,
                'site' => $siteId,
            ]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($result)) {
                return null;
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to get tracking user',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }

        return $result[0];
    }
}
