<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class TopologyEdges implements Countable, IteratorAggregate, JsonSerializable
{
    private readonly array $edges;

    private function __construct(TopologyEdge ...$edges)
    {
        $this->edges = $edges;
    }

    public static function from(TopologyEdge ...$edges): self
    {
        return new self(...$edges);
    }

    public function getIterator(): Generator
    {
        yield from $this->edges;
    }

    public function count(): int
    {
        return count($this->edges);
    }

    public function jsonSerialize(): array
    {
        return $this->edges;
    }
}
