<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Filter;

class RequestFilter
{
    private function __construct(
        private readonly int $siteId,
        private readonly ColumnFilterCollection $filterCollection,
    ) {
    }

    public static function fromQueryParams(int $siteId, string $filters): self
    {
        $urlDecodedFilters = urldecode($filters);
        $filters = json_decode($urlDecodedFilters, true, 512, JSON_THROW_ON_ERROR);
        $filterCollection = [];

        foreach ($filters as $filter) {
            $filterCollection[] = ColumnFilter::from(
                $filter['parameter'],
                $filter['value'],
                $filter['type'],
            );
        }

        return new self(
            $siteId,
            ColumnFilterCollection::from(...$filterCollection)
        );
    }

    public function getSiteId(): int
    {
        return $this->siteId;
    }

    public function getFilterCollection(): ColumnFilterCollection
    {
        return $this->filterCollection;
    }
}
