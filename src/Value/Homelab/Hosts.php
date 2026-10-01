<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class Hosts implements Countable, IteratorAggregate, JsonSerializable
{
    private readonly array $hosts;

    private function __construct(Host ...$hosts)
    {
        $this->hosts = $hosts;
    }

    public static function from(Host ...$hosts): self
    {
        return new self(...$hosts);
    }

    public function getIterator(): Generator
    {
        yield from $this->hosts;
    }

    public function count(): int
    {
        return count($this->hosts);
    }

    public function jsonSerialize(): array
    {
        return $this->hosts;
    }
}
