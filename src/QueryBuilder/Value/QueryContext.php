<?php

declare(strict_types=1);

namespace LukaLtaApi\QueryBuilder\Value;

use LukaLtaApi\QueryBuilder\QueryComponentBuilder;
use LukaLtaApi\Value\Filter\ColumnFilterCollection;
use LukaLtaApi\Value\Filter\FilterQueryBuilder;
use LukaLtaApi\Value\Request\RequestQueryParams;

class QueryContext
{
    private readonly FilterQueryBuilder $filterBuilder;

    private function __construct(
        public readonly int $siteId,
        private readonly RequestQueryParams $requestQueryParams,
        public readonly bool $isCountQuery,
        public readonly QueryComponentBuilder $componentBuilder,
        ColumnFilterCollection $filters,
    ) {
        $this->filterBuilder = FilterQueryBuilder::new()->applyFilters($filters);
    }

    public static function from(
        int $siteId,
        RequestQueryParams $requestQueryParams,
        bool $isCountQuery,
        QueryComponentBuilder $componentBuilder,
        ColumnFilterCollection $filters,
    ): self {
        return new self(
            $siteId,
            $requestQueryParams,
            $isCountQuery,
            $componentBuilder,
            $filters,
        );
    }

    public function getTimeStatement(): string
    {
        return $this->componentBuilder->buildTimeStatement($this->requestQueryParams);
    }

    public function getLimitStatement(): string
    {
        return $this->componentBuilder->buildLimitStatement(
            $this->requestQueryParams,
            $this->isCountQuery
        );
    }

    public function getOffsetStatement(): string
    {
        return $this->componentBuilder->buildOffsetStatement(
            $this->requestQueryParams,
            $this->isCountQuery
        );
    }

    public function getFilterFragment(): string
    {
        return $this->filterBuilder->getFragment();
    }

    /** @return array<int, mixed> */
    public function getFilterParams(): array
    {
        return $this->filterBuilder->getParams();
    }

    public function hasFilters(): bool
    {
        return !$this->filterBuilder->isEmpty();
    }

    public function getRequestQueryParams(): RequestQueryParams
    {
        return $this->requestQueryParams;
    }
}
