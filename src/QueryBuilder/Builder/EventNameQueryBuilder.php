<?php

declare(strict_types=1);

namespace LukaLtaApi\QueryBuilder\Builder;

use LukaLtaApi\QueryBuilder\MetricQueryBuilderInterface;
use LukaLtaApi\QueryBuilder\Value\QueryContext;
use LukaLtaApi\Value\Tracking\MetricParameter;

class EventNameQueryBuilder implements MetricQueryBuilderInterface
{
    public function supports(MetricParameter $parameter): bool
    {
        return $parameter === MetricParameter::EVENT_NAME;
    }

    public function build(QueryContext $context): string
    {
        $siteId        = $context->siteId;
        $timeStatement = $context->getTimeStatement();

        if ($context->isCountQuery) {
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

        $limitStatement  = $context->getLimitStatement();
        $offsetStatement = $context->getOffsetStatement();
        $filterFragment  = $context->hasFilters()
            ? 'AND ' . $context->getFilterFragment()
            : '';

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
                $filterFragment
            GROUP BY event_name
            ORDER BY count DESC
            $limitStatement
            $offsetStatement
        SQL;
    }
}
