<?php

declare(strict_types=1);

namespace LukaLtaApi\QueryBuilder\Builder;

use LukaLtaApi\QueryBuilder\Value\QueryContext;

class CommonCteBuilder
{
    public function buildSessionPageCounts(QueryContext $context): string
    {
        $siteId = $context->siteId;
        $timeStatement = $context->getTimeStatement();
        $filterFragment  = $context->hasFilters()
            ? 'AND ' . $context->getFilterFragment()
            : '';

        return <<<SQL
            SessionPageCounts AS (
                SELECT
                    session_id,
                    COUNT(*) as pageviews_in_session
                FROM events
                WHERE
                    site_id = $siteId
                    AND type = 'pageview'
                    $filterFragment
                    $timeStatement
                GROUP BY session_id
            )
        SQL;
    }

    public function buildEventTimes(QueryContext $context): string
    {
        $siteId = $context->siteId;
        $timeStatement = $context->getTimeStatement();
        $filterFragment  = $context->hasFilters()
            ? 'AND ' . $context->getFilterFragment()
            : '';

        return <<<SQL
            EventTimes AS (
                SELECT
                    e.session_id,
                    e.pathname,
                    e.occurred_on,
                    spc.pageviews_in_session,
                    LEAD(e.occurred_on) OVER (PARTITION BY e.session_id ORDER BY e.occurred_on) AS next_timestamp
                FROM events e
                LEFT JOIN SessionPageCounts spc ON e.session_id = spc.session_id
                WHERE
                    e.site_id = $siteId
                    $filterFragment
                    $timeStatement
            )
        SQL;
    }
}
