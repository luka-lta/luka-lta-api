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
                page_title as value,
                argMax(pathname, occurred_on) as pathname,
                COUNT(DISTINCT session_id) as unique_sessions
            FROM events
            WHERE
                site_id = $siteId
                AND page_title IS NOT NULL
                AND page_title <> ''
                $timeStatement
            GROUP BY page_title
        SQL;

        if ($context->isCountQuery) {
            return "SELECT COUNT(*) as totalCount FROM ($coreLogic)";
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
                any(pathname) as pathname,
                COUNT(DISTINCT session_id) as count,
                ROUND(COUNT(DISTINCT session_id) * 100.0 / SUM(COUNT(DISTINCT session_id)) OVER (), 2) as percentage,
                ROUND(countIf(DISTINCT session_id, pageviews_in_session = 1) * 100.0 / nullIf(COUNT(DISTINCT session_id), 0), 2) as bounceRate
            FROM TitleStatsWithSessions
            GROUP BY value
            ORDER BY count DESC
            $limitStatement
            $offsetStatement
        SQL;
    }
}
