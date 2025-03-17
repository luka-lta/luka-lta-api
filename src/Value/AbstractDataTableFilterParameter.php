<?php

declare(strict_types=1);

namespace LukaLtaApi\Value;

use Latitude\QueryBuilder\Query\SelectQuery;

use function Latitude\QueryBuilder\search;

abstract class AbstractDataTableFilterParameter
{
    private array $extraFilters = [];

    protected function __construct(
        private readonly int $page,
        private readonly int $pageSize,
        private readonly ?string $sortColumn,
        private readonly ?string $sortDirection,
        array $queryParameter,
    ) {
        $this->parseCustomFilter($queryParameter);
    }

    public static function parseFromQuery(array $query): static
    {
        return new static(
            (int)($query['page'] ?? 1),
            (int)($query['pageSize'] ?? 10),
            $query['sortColumn'] ?? null,
            $query['sortDirection'] ?? null,
            $query,
        );
    }

    private function parseCustomFilter(array $queryParameter): void
    {
        $names = $this->getExtraFilterName();

        foreach ($names as $name) {
            if (isset($queryParameter[$name])) {
                $value = $queryParameter[$name];

                // '*' ist ein Platzhalter für alle Werte
                if ($value === '*' || $value === '') {
                    continue;
                }

                $this->extraFilters[$name] = $value;
            }
        }
    }

    abstract protected function getExtraFilterName(): array;

    public function createSqlFilter(SelectQuery $query): SelectQuery
    {
        foreach ($this->extraFilters as $name => $value) {
            $query->where(search($name)->contains($value));
        }

        if ($this->sortDirection && $this->sortColumn) {
            $query->orderBy($this->sortColumn, $this->sortDirection);
        }

        $offset = ($this->page - 1) * $this->pageSize;
        $query->limit($this->pageSize)->offset($offset);

        return $query;
    }
}
