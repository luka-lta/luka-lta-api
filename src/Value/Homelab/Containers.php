<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class Containers implements Countable, IteratorAggregate, JsonSerializable
{
    private readonly array $containers;

    private function __construct(Container ...$containers)
    {
        $this->containers = $containers;
    }

    public static function from(Container ...$containers): self
    {
        return new self(...$containers);
    }

    public function getIterator(): Generator
    {
        yield from $this->containers;
    }

    public function count(): int
    {
        return count($this->containers);
    }

    public function jsonSerialize(): array
    {
        return $this->containers;
    }
}
