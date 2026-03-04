<?php

declare(strict_types=1);

namespace LukaLtaApi\QueryBuilder\Builder;

use LukaLtaApi\QueryBuilder\MetricQueryBuilderInterface;
use LukaLtaApi\QueryBuilder\SqlParameterMapper;
use LukaLtaApi\QueryBuilder\Value\QueryContext;
use LukaLtaApi\Value\Tracking\MetricParameter;

class DefaultQueryBuilder implements MetricQueryBuilderInterface
{
    public function __construct(
        private readonly CommonCteBuilder $cteBuilder,
        private readonly SqlParameterMapper $parameterMapper
    ) {}

    public function supports(MetricParameter $parameter): bool
    {
        return true; // Fallback für alle anderen Parameter
    }

    public function build(QueryContext $context): string
    {
        $siteId        = $context->siteId;
        $timeStatement = $context->getTimeStatement();
        $sqlParam      = $this->parameterMapper->map($context->getRequestQueryParams()->getParameter());
        $filterFragment       = $context->hasFilters()
            ? 'AND ' . $context->getFilterFragment()
            : '';

        if ($context->isCountQuery) {
            return <<<SQL
                SELECT COUNT(DISTINCT $sqlParam) as totalCount
                FROM events
                WHERE
                    site_id = $siteId
                    AND $sqlParam IS NOT NULL
                    AND $sqlParam <> ''
                    $filterFragment
                    $timeStatement
            SQL;
        }

        $sessionPageCountsCte = $this->cteBuilder->buildSessionPageCounts($context);
        $limitStatement       = $context->getLimitStatement();
        $offsetStatement      = $context->getOffsetStatement();


        return <<<SQL
            WITH $sessionPageCountsCte,
            SessionData AS (
                SELECT
                    $sqlParam AS value,
                    e.session_id,
                    spc.pageviews_in_session
                FROM events e
                LEFT JOIN SessionPageCounts spc ON e.session_id = spc.session_id
                WHERE
                    e.site_id = $siteId
                    AND $sqlParam IS NOT NULL
                    AND $sqlParam <> ''
                    $filterFragment
                    $timeStatement
            ),
            Aggregated AS (
                SELECT
                    value,
                    COUNT(DISTINCT session_id) AS unique_sessions,
                    COUNT(*) AS pageviews,
                    COUNT(DISTINCT CASE WHEN pageviews_in_session = 1 THEN session_id END) AS bounced_sessions
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
            $offsetStatement
        SQL;
    }
}
