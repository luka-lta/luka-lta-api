<?php

declare(strict_types=1);

namespace LukaLtaApi\QueryBuilder\Builder;

use LukaLtaApi\QueryBuilder\MetricQueryBuilderInterface;
use LukaLtaApi\QueryBuilder\Value\QueryContext;
use LukaLtaApi\Value\Tracking\MetricParameter;

class EntryExitPageQueryBuilder implements MetricQueryBuilderInterface
{
    public function __construct(
        private readonly CommonCteBuilder $cteBuilder
    ) {}

    public function supports(MetricParameter $parameter): bool
    {
        return $parameter === MetricParameter::ENTRY_PAGE
            || $parameter === MetricParameter::EXIT_PAGE;
    }

    public function build(QueryContext $context): string
    {
        $isEntry = $context->metricRequestData->getMetricParameter() === MetricParameter::ENTRY_PAGE;
        $orderDirection = $isEntry ? 'ASC' : 'DESC';
        $siteId = $context->siteId;
        $timeStatement = $context->getTimeStatement();

        $sessionPageCountsCte = $this->cteBuilder->buildSessionPageCounts($context);

        $baseCte = <<<SQL
            $sessionPageCountsCte,
            RelevantEvents AS (
                SELECT
                    e.*,
                    spc.pageviews_in_session
                FROM events e
                LEFT JOIN SessionPageCounts spc ON e.session_id = spc.session_id
                WHERE
                    e.site_id = $siteId
                    $timeStatement
            ),
            EventTimes AS (
                SELECT
                    session_id,
                    pathname,
                    occurred_on,
                    pageviews_in_session,
                    LEAD(occurred_on) OVER (PARTITION BY session_id ORDER BY occurred_on) AS next_timestamp,
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
                SELECT * FROM PageDurations WHERE row_num = 1
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

        if ($context->isCountQuery) {
            return <<<SQL
                WITH $baseCte
                SELECT COUNT(DISTINCT pathname) as totalCount FROM PathStats
            SQL;
        }

        $limitStatement = $context->getLimitStatement();
        $offsetStatement = $context->getOffsetStatement();

        return <<<SQL
            WITH $baseCte
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
}
