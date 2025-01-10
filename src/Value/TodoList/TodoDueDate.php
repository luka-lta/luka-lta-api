<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\TodoList;

use DateTimeImmutable;

class TodoDueDate
{
    private function __construct(
        private readonly ?DateTimeImmutable $dueDate
    ) {
    }

    public static function fromString(?string $dueDate): self
    {
        if ($dueDate === null) {
            return new self(null);
        }

        return new self(new DateTimeImmutable($dueDate));
    }

    public function toString(): string
    {
        return $this->dueDate->format('Y-m-d');
    }

    public function toDateObject(): DateTimeImmutable
    {
        return $this->dueDate;
    }
}
