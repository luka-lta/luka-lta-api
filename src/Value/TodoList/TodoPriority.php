<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\TodoList;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiValidationException;

class TodoPriority
{
    public const string PRIORITIE_LOW = 'low';
    public const string PRIORITIE_MEDIUM = 'medium';
    public const string PRIORITIE_HIGH = 'high';

    public const array VALID_PRIORITIES = [
        self::PRIORITIE_LOW,
        self::PRIORITIE_MEDIUM,
        self::PRIORITIE_HIGH,
    ];

    private function __construct(
        private readonly string $priority
    ) {
        if (!in_array($priority, self::VALID_PRIORITIES, true)) {
            throw new ApiValidationException(
                'Invalid Todo priority',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }
    }

    public static function fromString(string $priority): self
    {
        return new self($priority);
    }

    public function toString(): string
    {
        return $this->priority;
    }
}
