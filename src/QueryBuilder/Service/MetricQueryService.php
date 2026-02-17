<?php

declare(strict_types=1);

namespace LukaLtaApi\QueryBuilder\Service;

use LukaLtaApi\QueryBuilder\QueryBuilderFactory;
use LukaLtaApi\QueryBuilder\QueryComponentBuilder;
use LukaLtaApi\QueryBuilder\Value\QueryContext;
use LukaLtaApi\Value\WebTracking\Site\SiteMetricRequestData;

class MetricQueryService
{
    public function __construct(
        private readonly QueryBuilderFactory $queryBuilderFactory,
        private readonly QueryComponentBuilder $queryComponentBuilder,
    ) {
    }

    //TODO: Kann weg
    public function getQuery(int $siteId, SiteMetricRequestData $metricRequestData, bool $isCountQuery = false): string
    {
        $builder = $this->queryBuilderFactory->create($metricRequestData->getMetricParameter());

        $context = QueryContext::from(
            $siteId,
            $metricRequestData,
            $isCountQuery,
            $this->queryComponentBuilder
        );

        return $builder->build($context);

        /*$timeStatement = $this->getTimeStatement($metricRequestData);
        $limit = $metricRequestData->getLimit();
        $page = $metricRequestData->getPage();

        $limitStatement = !$isCountQuery
            ? ($limit ? "LIMIT $limit" : "LIMIT 100")
            : "";

        $validatedOffset = null;
        if (!$isCountQuery && $page !== null) {
            if ($page >= 1) {
                $pageOffset = ($page - 1) * ($limit ?? 100);
                $validatedOffset = $pageOffset;
            }
        }

        $offsetStatement = (!$isCountQuery && $validatedOffset !== null)
            ? "OFFSET $validatedOffset"
            : "";

        if ($metricRequestData->getMetricParameter() === MetricParameter::EVENT_NAME) {
            if ($isCountQuery) {
                return <<<SQL
                  SELECT COUNT(DISTINCT event_name) as totalCount
                  FROM events
                  WHERE
                    site_id = {$siteId}
                    AND event_name IS NOT NULL 
                    AND event_name <> ''
                    $timeStatement
                    AND type = 'custom_event'
                SQL;
            }
            return <<<SQL
                SELECT
                  event_name as value,
                  COUNT(*) as count,
                  ROUND(COUNT(DISTINCT(session_id)) * 100.0 / SUM(COUNT(DISTINCT(session_id))) OVER (), 2) as percentage
                FROM events
                WHERE
                  site_id = $siteId
                  AND event_name IS NOT NULL 
                  AND event_name <> ''
                  $timeStatement
                  AND type = 'custom_event'
                GROUP BY event_name ORDER BY count desc
                $limitStatement
                $offsetStatement;
            SQL;
        }

        if ($metricRequestData->getMetricParameter() === MetricParameter::PAGE_TITLE) {
            $corePageTitleLogic = <<<SQL
              SELECT
                  page_title as value,
                  argMax(pathname, occurred_on) as pathname,
                  COUNT(DISTINCT session_id) as unique_sessions
              FROM events
              WHERE
                  site_id = $siteId
                  AND page_title IS NOT NULL
                  AND page_title <> ''
                  -- AND type = 'pageview'
                  $timeStatement
              GROUP BY page_title
            SQL;

            if ($isCountQuery) {
                return <<<SQL
                    SELECT COUNT(*) as totalCount FROM ($corePageTitleLogic);
                SQL;
            }

            return <<<SQL
                WITH SessionPageCounts AS (
                  SELECT
                      session_id,
                      COUNT() as pageviews_in_session
                  FROM events
                  WHERE
                      site_id = $siteId
                      AND type = 'pageview'
                      $timeStatement
                  GROUP BY session_id
              ),
              TitleStatsWithSessions AS (
                  SELECT
                      e.page_title as value,
                      e.pathname as pathname,
                      e.session_id,
                      spc.pageviews_in_session
                  FROM events e
                  LEFT JOIN SessionPageCounts spc ON e.session_id = spc.session_id
                  WHERE
                      e.site_id = $siteId
                      AND e.page_title IS NOT NULL
                      AND e.page_title <> ''
                      $timeStatement
              )
              SELECT
                  value,       -- This is page_title
                  any(pathname) as pathname,    -- This is the representative pathname
                  COUNT(DISTINCT session_id) as count,
                  ROUND(
                      COUNT(DISTINCT session_id) * 100.0 / SUM(COUNT(DISTINCT session_id)) OVER (),
                      2
                  ) as percentage,
                  ROUND(
                      countIf(DISTINCT session_id, pageviews_in_session = 1) * 100.0 / nullIf(COUNT(DISTINCT session_id), 0),
                      2
                  ) as bounceRate
              FROM TitleStatsWithSessions
              GROUP BY value
              ORDER BY count DESC
              $limitStatement
              $offsetStatement;
            SQL;
        }

        if ($metricRequestData->getMetricParameter() === MetricParameter::EXIT_PAGE || $metricRequestData->getMetricParameter() === MetricParameter::ENTRY_PAGE) {
            $isEntry = $metricRequestData->getMetricParameter() === MetricParameter::ENTRY_PAGE;
            $orderDirection = $isEntry ? 'ASC' : 'DESC';

            $baseCteQuery = <<<SQL
                SessionPageCounts AS (
                          SELECT
                              session_id,
                              COUNT(*) as pageviews_in_session
                          FROM events
                          WHERE
                              site_id = $siteId
                              AND type = 'pageview'
                              $timeStatement
                          GROUP BY session_id
                      ),
                      RelevantEvents AS (
                          SELECT
                              e.*,
                              spc.pageviews_in_session
                          FROM events e
                          LEFT JOIN SessionPageCounts spc ON e.session_id = spc.session_id
                          WHERE
                              e.site_id = $siteId
                              -- AND type = 'pageview'
                              $timeStatement
                      ),
                      EventTimes AS (
                          SELECT
                              session_id,
                              pathname,
                              occurred_on,
                              pageviews_in_session,
                              LEAD(occurred_on) OVER (
                                PARTITION BY session_id 
                                ORDER BY occurred_on
                              ) AS next_timestamp,
                              ROW_NUMBER() OVER (PARTITION BY session_id ORDER BY occurred_on $orderDirection) as row_num
                          FROM RelevantEvents
                      ),
                      PageDurations AS (
                          SELECT
                              session_id,
                              pathname,
                              occurred_on,
                              next_timestamp,
                              row_num,
                              pageviews_in_session,
                              IF(next_timestamp IS NULL, 0, TIMESTAMPDIFF(SECOND, occurred_on, next_timestamp)) as time_diff_seconds
                          FROM EventTimes
                      ),
                      FilteredDurations AS (
                          SELECT *
                          FROM PageDurations
                          WHERE row_num = 1
                      ),
                      PathStats AS (
                          SELECT
                              pathname,
                              COUNT(DISTINCT session_id) as unique_sessions,
                              COUNT(*) as visits,
                              AVG(IF(time_diff_seconds < 0, 0, IF(time_diff_seconds > 1800, 1800, time_diff_seconds))) as avg_timeOnPageSeconds,
                              COUNT(DISTINCT CASE WHEN pageviews_in_session = 1 THEN session_id END) as bounced_sessions
                          FROM FilteredDurations
                          WHERE pathname IS NOT NULL AND pathname <> ''
                          GROUP BY pathname
                      )
            SQL;

            if ($isCountQuery) {
                return <<<SQL
                  WITH $baseCteQuery
                  SELECT COUNT(DISTINCT pathname) as totalCount FROM PathStats;
                SQL;
            }

            return <<<SQL
                WITH $baseCteQuery
                SELECT
                    pathname as value,
                    unique_sessions as count,
                    ROUND((unique_sessions / SUM(unique_sessions) OVER ()) * 100, 2) as percentage,
                    visits as pageviews,
                    ROUND((visits / SUM(visits) OVER ()) * 100, 2) as pageviewsPercentage,
                    avg_timeOnPageSeconds as timeOnPageSeconds,
                    ROUND((bounced_sessions / NULLIF(unique_sessions, 0)) * 100, 2) as bounceRate
                FROM PathStats
                ORDER BY unique_sessions DESC
                $limitStatement
                $offsetStatement
            SQL;
        }

        if ($metricRequestData->getMetricParameter() === MetricParameter::PATHNAME) {
            $baseCteQuery = <<<SQL
                SessionPageCounts AS (
                          SELECT
                              session_id,
                              COUNT() as pageviews_in_session
                          FROM events
                          WHERE
                              site_id = $siteId
                              AND type = 'pageview'
                              ${timeStatement}
                          GROUP BY session_id
                      ),
                      EventTimes AS (
                          SELECT
                              e.session_id,
                              e.pathname,
                              e.occurred_on,
                              spc.pageviews_in_session,
                              LEAD(e.occurred_on) OVER (
                                PARTITION BY e.session_id 
                                ORDER BY e.occurred_on
                            ) AS next_timestamp

                          FROM events e
                          LEFT JOIN SessionPageCounts spc ON e.session_id = spc.session_id
                          WHERE
                            e.site_id = $siteId
                            -- AND type = 'pageview'
                            $timeStatement
                      ),
                      PageDurations AS (
                          SELECT
                              session_id,
                              pathname,
                              occurred_on,
                              next_timestamp,
                              pageviews_in_session,
                              if(isNull(next_timestamp), 0, TIMESTAMPDIFF(
                                SECOND, 
                                occurred_on, 
                                next_timestamp
                            ) AS time_diff_seconds
                          FROM EventTimes
                      ),
                      PathStats AS (
                          SELECT
                              pathname,
                              count() as visits,
                              count(DISTINCT session_id) as unique_sessions,
                              avg(if(time_diff_seconds < 0, 0, if(time_diff_seconds > 1800, 1800, time_diff_seconds))) as avg_timeOnPageSeconds,
                              countIf(DISTINCT session_id, pageviews_in_session = 1) as bounced_sessions
                          FROM PageDurations
                          GROUP BY pathname
                      )
            SQL;

            if ($isCountQuery) {
                return <<<SQL
                  WITH ${baseCteQuery}
                  SELECT COUNT(DISTINCT pathname) as totalCount FROM PathStats;
                SQL;
            }

            return <<<SQL
                WITH $baseCteQuery
                SELECT
                    pathname as value,
                    unique_sessions as count,
                    round((unique_sessions / sum(unique_sessions) OVER ()) * 100, 2) as percentage,
                    visits as pageviews,
                    round((visits / sum(visits) OVER ()) * 100, 2) as pageviewsPercantage,
                    avg_timeOnPageSeconds as timeOnPageSeconds,
                    round((bounced_sessions / nullIf(unique_sessions, 0)) * 100, 2) as bounceRate
                FROM PathStats
                ORDER BY unique_sessions DESC
                $limitStatement
                $offsetStatement;
            SQL;
        }

        $sqlParam = $this->getSqlParam($metricRequestData);
        if ($isCountQuery) {
            return <<<SQL
                SELECT COUNT(DISTINCT $sqlParam) as totalCount
                FROM events
                WHERE
                    site_id = $siteId
                    AND $sqlParam IS NOT NULL
                    AND $sqlParam <> ''
                    $timeStatement;
            SQL;
        }

        return <<<SQL
            WITH SessionPageCounts AS (
                SELECT
                    session_id,
                    COUNT(*) AS pageviews_in_session
                FROM events
                WHERE
                    site_id = $siteId
                    AND type = 'pageview'
                    $timeStatement
                GROUP BY session_id
            ),
            SessionData AS (
                SELECT
                    $sqlParam AS value,
                    e.session_id,
                    spc.pageviews_in_session
                FROM events e
                LEFT JOIN SessionPageCounts spc 
                    ON e.session_id = spc.session_id
                WHERE
                    e.site_id = $siteId
                    AND $sqlParam IS NOT NULL
                    AND $sqlParam <> ''
                    $timeStatement
            ),
            Aggregated AS (
                SELECT
                    value,
                    COUNT(DISTINCT session_id) AS unique_sessions,
                    COUNT(*) AS pageviews,
                    COUNT(DISTINCT CASE 
                        WHEN pageviews_in_session = 1 THEN session_id 
                    END) AS bounced_sessions
                FROM SessionData
                GROUP BY value
            )
            SELECT
                value,
                unique_sessions AS count,
                ROUND(unique_sessions / SUM(unique_sessions) OVER () * 100, 2) AS percentage,
                pageviews,
                ROUND(pageviews / SUM(pageviews) OVER () * 100, 2) AS pageviewsPercantage,
                ROUND(bounced_sessions / NULLIF(unique_sessions, 0) * 100, 2) AS bounceRate
            FROM Aggregated
            ORDER BY count DESC
            $limitStatement
            $offsetStatement;
        SQL;*/
    }

   /* private function getTimeStatement(SiteMetricRequestData $metricRequestData): string
    {
        $pastMinutesStart = $metricRequestData->getPastMinutesStart()?->format('c');
        $pastMinutesEnd = $metricRequestData->getPastMinutesEnd()?->format('c');
        $startDate = $metricRequestData->getStartDate();
        $endDate = $metricRequestData->getEndDate();
        $timeZone = $metricRequestData->getTimeZone();

        // 1️⃣ Past Minutes Range
        if ($pastMinutesStart !== null && $metricRequestData->getPastMinutesEnd() !== null) {
            $now = new DateTime('now', new DateTimeZone('UTC'));
            $startTimestamp = $now->modify("-$pastMinutesStart minutes")->format('Y-m-d H:i:s');
            $now = new DateTime('now', new DateTimeZone('UTC')); // Reset für end
            $endTimestamp = $now->modify("-$pastMinutesEnd minutes")->format('Y-m-d H:i:s');

            return "AND occurred_on > '$startTimestamp' AND occurred_on <= '$endTimestamp'";
        }

        // 2️⃣ Date + Timezone Range
        if ($startDate && $endDate && $timeZone) {
            $startUtc = $startDate->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

            $now = new DateTime('now', $timeZone);

            if ($endDate->format('Y-m-d') === $now->format('Y-m-d')) {
                $endUtc = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            } else {
                $endUtc = $endDate->setTime(0, 0)
                    ->modify('+1 day')
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format('Y-m-d H:i:s');
            }

            return "AND occurred_on >= '$startUtc' AND occurred_on < '$endUtc'";
        }

        return '';
    }

    private function getSqlParam(SiteMetricRequestData $metricRequestData): string
    {
        $paramValue = $metricRequestData->getMetricParameter()->value;
        if (str_starts_with($metricRequestData->getMetricParameter()->value, 'utm_') || str_starts_with($metricRequestData->getMetricParameter()->value, 'url_param:')) {
            if (str_starts_with($metricRequestData->getMetricParameter()->value, 'url_param:')) {
                $paramName = substr($metricRequestData->getMetricParameter()->value, strlen('url_param:'));
                return "url_parameters->>'$.{$paramName}'"; // MySQL JSON path
            }

            return "url_parameters->>'$.{$paramValue}'";
        }

        switch ($metricRequestData->getMetricParameter()->value) {
            case MetricParameter::REFERRER:
                return 'domainWithoutWWW(referrer))';
            case MetricParameter::ENTRY_PAGE:
                return "(SELECT pathname FROM events e2 WHERE e2.session_id = events.session_id ORDER BY occurred_on ASC LIMIT 1)";
            case MetricParameter::EXIT_PAGE:
                return "(SELECT pathname FROM events e2 WHERE e2.session_id = events.session_id ORDER BY occurred_on DESC LIMIT 1)";
            case MetricParameter::DIMENSIONS:
                return "CONCAT(CAST(screen_width AS CHAR), 'x', CAST(screen_height AS CHAR))";
            case MetricParameter::CITY:
                return "CONCAT(CAST(region AS CHAR), '-', CAST(city AS CHAR))";
            case MetricParameter::BROWSER_VERSION:
                return "CONCAT(CAST(browser AS CHAR), ' ', CAST(browser_version AS CHAR))";
            case MetricParameter::OS_VERSION:
                return "CASE
                    WHEN CONCAT(CAST(os AS CHAR), ' ', CAST(os_version AS CHAR)) = 'Windows 10'
                    THEN 'Windows 10/11'
                    ELSE CONCAT(CAST(os AS CHAR), ' ', CAST(os_version AS CHAR))
                END";
        }

        return $paramValue;
    }*/
}
