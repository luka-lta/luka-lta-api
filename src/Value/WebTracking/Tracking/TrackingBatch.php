<?php

namespace LukaLtaApi\Value\WebTracking\Tracking;

use Countable;
use Generator;
use IteratorAggregate;

class TrackingBatch implements IteratorAggregate, Countable
{
    private readonly array $events;
    private const int BATCH_SIZE = 5000;

    private function __construct(PageViewEvent ...$pageViewEvent)
    {
        $this->events = $pageViewEvent;
    }

    public static function from(PageViewEvent ...$pageViewEvent): self
    {
        return new self(...$pageViewEvent);
    }

    public function count(): int
    {
        return count($this->events);
    }

    public function getIterator(): Generator
    {
        yield from $this->events;
    }
}
