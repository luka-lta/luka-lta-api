<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class TopologyNodes implements Countable, IteratorAggregate, JsonSerializable
{
    private readonly array $nodes;

    private function __construct(TopologyNode ...$nodes)
    {
        $this->nodes = $nodes;
    }

    public static function from(TopologyNode ...$nodes): self
    {
        return new self(...$nodes);
    }

    public function getIterator(): Generator
    {
        yield from $this->nodes;
    }

    public function count(): int
    {
        return count($this->nodes);
    }

    public function jsonSerialize(): array
    {
        return $this->nodes;
    }
}
