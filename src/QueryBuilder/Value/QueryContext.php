<?php

declare(strict_types=1);

namespace LukaLtaApi\QueryBuilder\Value;

use LukaLtaApi\QueryBuilder\QueryComponentBuilder;
use LukaLtaApi\Value\WebTracking\Site\SiteMetricRequestData;

class QueryContext
{
    private function __construct(
        public readonly int $siteId,
        public readonly SiteMetricRequestData $metricRequestData,
        public readonly bool $isCountQuery,
        public readonly QueryComponentBuilder $componentBuilder
    ) {}

    public static function from(
        int $siteId,
        SiteMetricRequestData $metricRequestData,
        bool $isCountQuery,
        QueryComponentBuilder $componentBuilder
    ): self {
        return new self(
            $siteId,
            $metricRequestData,
            $isCountQuery,
            $componentBuilder
        );
    }

    public function getTimeStatement(): string
    {
        return $this->componentBuilder->buildTimeStatement($this->metricRequestData);
    }

    public function getLimitStatement(): string
    {
        return $this->componentBuilder->buildLimitStatement(
            $this->metricRequestData,
            $this->isCountQuery
        );
    }

    public function getOffsetStatement(): string
    {
        return $this->componentBuilder->buildOffsetStatement(
            $this->metricRequestData,
            $this->isCountQuery
        );
    }
}
