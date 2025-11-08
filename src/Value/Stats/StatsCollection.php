<?php

namespace LukaLtaApi\Value\Stats;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class StatsCollection implements Countable, IteratorAggregate, JsonSerializable
{
    private readonly array $stats;

    private function __construct(AbstractStat ...$stats)
    {
        $this->stats = $stats;
    }

    public static function from(AbstractStat ...$stat): self
    {
        return new self(...$stat);
    }

    public function getIterator(): Generator
    {
        yield from $this->stats;
    }

    public function count(): int
    {
        return count($this->stats);
    }

    public function toFrontend(): array
    {
        $stats = [];

        foreach ($this->stats as $stat) {
            $stats[] = [
                $stat->getLabel() => $stat->getLabelValue(),
                'amount' => $stat->getAmount(),
                'percentage' => $stat->getPercentage(),
            ];
        }

        return $stats;
    }

    public function jsonSerialize(): array
    {
        return $this->stats;
    }
}
