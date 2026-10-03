<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class Events implements Countable, IteratorAggregate, JsonSerializable
{
    private readonly array $events;

    private function __construct(Event ...$events)
    {
        $this->events = $events;
    }

    public static function from(Event ...$events): self
    {
        return new self(...$events);
    }

    public function getIterator(): Generator
    {
        yield from $this->events;
    }

    public function count(): int
    {
        return count($this->events);
    }

    public function jsonSerialize(): array
    {
        return $this->events;
    }
}
