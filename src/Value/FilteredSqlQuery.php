<?php

namespace LukaLtaApi\Value;

class FilteredSqlQuery
{
    private function __construct(
        private readonly string $query,
        private readonly array $bindings
    ) {
    }

    public static function from(string $query, array $bindings = []): self
    {
        return new self($query, $bindings);
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function getQuery(): string
    {
        return $this->query;
    }
}
