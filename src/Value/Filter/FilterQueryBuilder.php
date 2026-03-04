<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Filter;

class FilterQueryBuilder
{
    /** @var string[] */
    private array $fragments = [];

    /** @var array<int, mixed> */
    private array $params = [];

    public static function new(): self
    {
        return new self();
    }

    public function applyFilters(ColumnFilterCollection $filters): self
    {
        foreach ($filters as $filter) {
            $this->applyFilter($filter);
        }

        return $this;
    }

    public function getFragment(): string
    {
        return implode(' AND ', $this->fragments);
    }

    /** @return array<int, mixed> */
    public function getParams(): array
    {
        return $this->params;
    }

    public function isEmpty(): bool
    {
        return empty($this->fragments);
    }

    private function applyFilter(ColumnFilter $filter): void
    {
        match ($filter->getCondition()) {
            FilterCondition::EQUALS       => $this->applyEquals($filter),
            FilterCondition::NOT_EQUALS   => $this->applyNotEquals($filter),
            FilterCondition::CONTAINS     => $this->applyContains($filter),
            FilterCondition::NOT_CONTAINS => $this->applyNotContains($filter),
            FilterCondition::GREATER_THAN => $this->applyGreaterThan($filter),
            FilterCondition::LESS_THAN    => $this->applyLessThan($filter),
            FilterCondition::REGEX        => $this->applyRegex($filter),
            FilterCondition::NOT_REGEX    => $this->applyNotRegex($filter),
        };
    }

    private function applyEquals(ColumnFilter $filter): void
    {
        $col    = $filter->getParameter();
        $values = $filter->getValues();

        if (count($values) === 1) {
            $this->push("{$col} = ?", $values);
            return;
        }

        $this->push("{$col} IN (" . $this->placeholders($values) . ")", $values);
    }

    private function applyNotEquals(ColumnFilter $filter): void
    {
        $col    = $filter->getParameter();
        $values = $filter->getValues();

        if (count($values) === 1) {
            $this->push("{$col} != ?", $values);
            return;
        }

        $this->push("{$col} NOT IN (" . $this->placeholders($values) . ")", $values);
    }

    private function applyContains(ColumnFilter $filter): void
    {
        $col           = $filter->getParameter();
        $values        = $filter->getValues();
        $parts         = array_fill(0, count($values), "{$col} LIKE ?");
        $wrappedValues = array_map(static fn($v) => '%' . $v . '%', $values);

        $this->push('(' . implode(' OR ', $parts) . ')', $wrappedValues);
    }

    private function applyNotContains(ColumnFilter $filter): void
    {
        $col           = $filter->getParameter();
        $wrappedValues = array_map(static fn($v) => '%' . $v . '%', $filter->getValues());

        foreach ($wrappedValues as $value) {
            $this->push("{$col} NOT LIKE ?", [$value]);
        }
    }

    private function applyGreaterThan(ColumnFilter $filter): void
    {
        $this->push($filter->getParameter() . ' > ?', [$filter->getValues()[0]]);
    }

    private function applyLessThan(ColumnFilter $filter): void
    {
        $this->push($filter->getParameter() . ' < ?', [$filter->getValues()[0]]);
    }

    private function applyRegex(ColumnFilter $filter): void
    {
        $col   = $filter->getParameter();
        $parts = array_fill(0, count($filter->getValues()), "{$col} REGEXP ?");

        $this->push('(' . implode(' OR ', $parts) . ')', $filter->getValues());
    }

    private function applyNotRegex(ColumnFilter $filter): void
    {
        $col = $filter->getParameter();

        foreach ($filter->getValues() as $pattern) {
            $this->push("{$col} NOT REGEXP ?", [$pattern]);
        }
    }

    private function push(string $fragment, array $params): void
    {
        $this->fragments[] = $fragment;
        array_push($this->params, ...$params);
    }

    private function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }
}
