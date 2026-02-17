<?php

declare(strict_types=1);

namespace LukaLtaApi\QueryBuilder\Builder;

use LukaLtaApi\QueryBuilder\MetricQueryBuilderInterface;
use LukaLtaApi\QueryBuilder\Value\QueryContext;
use LukaLtaApi\Value\Tracking\MetricParameter;

class PathNameQueryBuilder implements MetricQueryBuilderInterface
{
    public function __construct(
        private readonly CommonCteBuilder $cteBuilder
    ) {}

    public function supports(MetricParameter $parameter): bool
    {
        return $parameter === MetricParameter::PATHNAME;
    }

    public function build(QueryContext $context): string
    {
        $sessionPageCountsCte = $this->cteBuilder->buildSessionPageCounts($context);
        $eventTimesCte = $this->cteBuilder->buildEventTimes($context);

        $baseCte = <<<SQL
            $sessionPageCountsCte,
            $eventTimesCte,
            PageDurations AS (
                SELECT
                    session_id,
                    pathname,
                    occurred_on,
                    next_timestamp,
                    pageviews_in_session,
                    if(isNull(next_timestamp), 0, TIMESTAMPDIFF(SECOND, occurred_on, next_timestamp)) AS time_diff_seconds
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
                round((unique_sessions / sum(unique_sessions) OVER ()) * 100, 2) as percentage,
                visits as pageviews,
                round((visits / sum(visits) OVER ()) * 100, 2) as pageviewsPercantage,
                avg_timeOnPageSeconds as timeOnPageSeconds,
                round((bounced_sessions / nullIf(unique_sessions, 0)) * 100, 2) as bounceRate
            FROM PathStats
            ORDER BY unique_sessions DESC
            $limitStatement
            $offsetStatement
        SQL;
    }
}
