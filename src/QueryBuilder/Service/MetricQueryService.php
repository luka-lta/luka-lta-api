<?php

declare(strict_types=1);

namespace LukaLtaApi\QueryBuilder\Service;

use LukaLtaApi\QueryBuilder\QueryBuilderFactory;
use LukaLtaApi\QueryBuilder\QueryComponentBuilder;
use LukaLtaApi\QueryBuilder\Value\QueryContext;
use LukaLtaApi\Value\Filter\ColumnFilterCollection;
use LukaLtaApi\Value\Request\RequestQueryParams;

class MetricQueryService
{
    public function __construct(
        private readonly QueryBuilderFactory $queryBuilderFactory,
        private readonly QueryComponentBuilder $queryComponentBuilder,
    ) {
    }

    public function getQuery(
        int $siteId,
        RequestQueryParams $requestQueryParams,
        bool $isCountQuery = false,
        ?ColumnFilterCollection $filters = null,
    ): array {
        $builder = $this->queryBuilderFactory->create($requestQueryParams->getParameter());

        $context = QueryContext::from(
            $siteId,
            $requestQueryParams,
            $isCountQuery,
            $this->queryComponentBuilder,
            $filters ?? ColumnFilterCollection::from(),
        );

        return [
            'sql'    => $builder->build($context),
            'params' => $context->getFilterParams(),
        ];
    }
}
