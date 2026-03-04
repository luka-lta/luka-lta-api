<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Filter;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

class ColumnFilterCollection implements IteratorAggregate, JsonSerializable, Countable
{
    private readonly array $filters;

    private function __construct(ColumnFilter ...$filters)
    {
        $this->filters = $filters;
    }

    public static function from(ColumnFilter ...$columnFilter): self
    {
        return new self(...$columnFilter);
    }

    public function getIterator(): Generator
    {
        yield from $this->filters;
    }

    public function count(): int
    {
        return count($this->filters);
    }

    public function jsonSerialize(): array
    {
        return $this->filters;
    }

    public function toArray(): array
    {
        return array_map(static fn(ColumnFilter $filter) => $filter->toArray(), $this->filters);
    }
}
