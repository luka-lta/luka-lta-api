<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class MetricSeries implements Countable, IteratorAggregate, JsonSerializable
{
    private readonly array $points;

    private function __construct(MetricPoint ...$points)
    {
        $this->points = $points;
    }

    public static function from(MetricPoint ...$points): self
    {
        return new self(...$points);
    }

    public function getIterator(): Generator
    {
        yield from $this->points;
    }

    public function count(): int
    {
        return count($this->points);
    }

    public function jsonSerialize(): array
    {
        return $this->points;
    }
}
