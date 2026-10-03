<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Alert;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class Alerts implements Countable, IteratorAggregate, JsonSerializable
{
    private readonly array $alerts;

    private function __construct(Alert ...$alerts)
    {
        $this->alerts = $alerts;
    }

    public static function from(Alert ...$alerts): self
    {
        return new self(...$alerts);
    }

    public function getIterator(): Generator
    {
        yield from $this->alerts;
    }

    public function count(): int
    {
        return count($this->alerts);
    }

    public function jsonSerialize(): array
    {
        return $this->alerts;
    }
}
