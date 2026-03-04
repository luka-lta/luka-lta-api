<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Filter;

enum FilterCondition: string
{
    case EQUALS = 'equals';
    case NOT_EQUALS = 'not_equals';
    case CONTAINS = 'contains';
    case NOT_CONTAINS = 'not_contains';
    case GREATER_THAN = 'greater_than';
    case LESS_THAN = 'less_than';
    case REGEX = 'regex';
    case NOT_REGEX = 'not_regex';

    public static function fromName(string $conditionType): self
    {
        return match ($conditionType) {
            'equals' => self::EQUALS,
            'not_equals' => self::NOT_EQUALS,
            'contains' => self::CONTAINS,
            'not_contains' => self::NOT_CONTAINS,
            'greater_than' => self::GREATER_THAN,
            'less_than' => self::LESS_THAN,
            'regex' => self::REGEX,
            'not_regex' => self::NOT_REGEX,
        };
    }

    public function asSql(): ?string
    {
        switch ($this->value) {
            case 'equals':
                return '=';
            case 'not_equals':
                return '!=';
            case 'contains':
                return 'LIKE';
            case 'not_contains':
                return 'NOT LIKE';
            case 'greater_than':
                return '>';
            case 'less_than':
                return '<';
            case 'regex':
            case 'not_regex':
                return null;
            default:
                throw new \InvalidArgumentException('Invalid filter condition: ' . $this->value);
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
