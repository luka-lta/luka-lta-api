<?php

namespace LukaLtaApi\Service;

use LukaLtaApi\Value\FilteredSqlQuery;

class FilterService
{
    public function filter(string $key, string $operator, mixed $value): FilteredSqlQuery
    {
        $allowedOperators = ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'IN'];

        if (!in_array($operator, $allowedOperators, true)) {
            throw new \InvalidArgumentException("Ungültiger Operator: $operator");
        }

        // Parameter-Key und SQL-Bedingung erstellen
        $placeholder = ':' . str_replace('.', '_', $key); // Vermeidet Konflikte mit Bindings
        $condition = ($operator === 'IN' && is_array($value))
            ? "$key IN (" . implode(', ', array_fill(0, count($value), $placeholder)) . ")"
            : "$key $operator $placeholder";

        // Bindings erstellen
        $bindings = is_array($value) ? array_combine(array_map(static fn($i) => "$placeholder$i", array_keys($value)), $value) : [$placeholder => $value];

        return FilteredSqlQuery::from("WHERE $condition", $bindings);
    }
}
