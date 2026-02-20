<?php

declare(strict_types=1);

namespace LukaLtaApi\QueryBuilder\Builder;

use LukaLtaApi\QueryBuilder\MetricQueryBuilderInterface;
use LukaLtaApi\QueryBuilder\Value\QueryContext;
use LukaLtaApi\Value\Tracking\MetricParameter;

class PageTitleQueryBuilder implements MetricQueryBuilderInterface
{
    public function __construct(
        private readonly CommonCteBuilder $cteBuilder
    ) {}

    public function supports(MetricParameter $parameter): bool
    {
        return $parameter === MetricParameter::PAGE_TITLE;
    }

    public function build(QueryContext $context): string
    {
        $siteId = $context->siteId;
        $timeStatement = $context->getTimeStatement();

        $coreLogic = <<<SQL
            SELECT
                e.page_title AS value,
                e.pathname AS pathname, -- der "letzte" Pfad pro page_title
                COUNT(DISTINCT e.session_id) AS unique_sessions
            FROM events e
            INNER JOIN (
                -- Finde das zuletzt aufgetretene Ereignis pro page_title
                SELECT
                    page_title,
                    MAX(occurred_on) AS last_occurred
                FROM events
                WHERE site_id = $siteId
                    AND page_title IS NOT NULL
                    AND page_title <> ''
                    $timeStatement
                GROUP BY page_title
            ) latest
                ON e.page_title = latest.page_title
                AND e.occurred_on = latest.last_occurred
            WHERE e.site_id = $siteId
                AND e.page_title IS NOT NULL
                AND e.page_title <> ''
                $timeStatement
            GROUP BY e.page_title, e.pathname
        SQL;

        if ($context->isCountQuery) {
            return "SELECT COUNT(*) as totalCount FROM ($coreLogic) AS count_subquery";
        }

        $sessionPageCountsCte = $this->cteBuilder->buildSessionPageCounts($context);
        $limitStatement = $context->getLimitStatement();
        $offsetStatement = $context->getOffsetStatement();

        return <<<SQL
            WITH $sessionPageCountsCte,
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
                value,
                MIN(pathname) as pathname,
                COUNT(DISTINCT session_id) as count,
                ROUND(COUNT(DISTINCT session_id) * 100.0 / SUM(COUNT(DISTINCT session_id)) OVER (), 2) as percentage,
                ROUND(
                    COUNT(DISTINCT CASE 
                        WHEN pageviews_in_session = 1 THEN session_id 
                    END) * 100.0 
                    / NULLIF(COUNT(DISTINCT session_id), 0),
                    2
                ) as bounceRate
            FROM TitleStatsWithSessions
            GROUP BY value
            ORDER BY count DESC
            $limitStatement
            $offsetStatement
        SQL;
    }
}
