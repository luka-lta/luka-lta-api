<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Filter;

class ColumnFilter
{
    private function __construct(
        private readonly string $parameter,
        private readonly array $values,
        private readonly FilterCondition $condition
    ) {
    }

    public static function from(
        string $parameter,
        array $values,
        string $condition,
    ): self {
        return new self(
            $parameter,
            $values,
            FilterCondition::fromName($condition)
        );
    }

    public function toArray(): array
    {
        return [
            'parameter' => $this->parameter,
            'values' => $this->values,
            'condition' => $this->condition->value,
            'conditionSql' => $this->condition->asSql()
        ];
    }

    public function getParameter(): string
    {
        return $this->parameter;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function getCondition(): FilterCondition
    {
        return $this->condition;
    }
}
