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
    }
}
