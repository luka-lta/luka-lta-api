<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\TodoList;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiValidationException;

class TodoStatus
{
    public const string STATUS_OPEN = 'open';
    public const string STATUS_IN_PROGRESS = 'in_progress';
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_ARCHIVED = 'archived';

    public const array VALID_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_ARCHIVED,
    ];

    private function __construct(
        private readonly string $status
    ) {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new ApiValidationException(
                'Invalid Todo status',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }
    }

    public static function fromString(?string $status): self
    {
        if ($status === null) {
            return new self(self::STATUS_OPEN);
        }

        return new self($status);
    }

    public function toString(): string
    {
        return $this->status;
    }
}
