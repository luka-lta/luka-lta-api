<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

/** @implements IteratorAggregate<int, Offer> */
class Offers implements IteratorAggregate, Countable, JsonSerializable
{
    /** @var list<Offer> */
    private readonly array $offers;

    private function __construct(Offer ...$offers)
    {
        $this->offers = array_values($offers);
    }

    public static function from(Offer ...$offers): self
    {
        return new self(...$offers);
    }

    public function getIterator(): Generator
    {
        yield from $this->offers;
    }

    public function count(): int
    {
        return count($this->offers);
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(static fn(Offer $offer): array => $offer->toArray(), $this->offers);
    }

    /** @return list<array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
