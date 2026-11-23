<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

/** @implements IteratorAggregate<int, Invoice> */
class Invoices implements IteratorAggregate, Countable, JsonSerializable
{
    /** @var list<Invoice> */
    private readonly array $invoices;

    private function __construct(Invoice ...$invoices)
    {
        $this->invoices = array_values($invoices);
    }

    public static function from(Invoice ...$invoices): self
    {
        return new self(...$invoices);
    }

    public function getIterator(): Generator
    {
        yield from $this->invoices;
    }

    public function count(): int
    {
        return count($this->invoices);
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(static fn(Invoice $invoice): array => $invoice->toArray(), $this->invoices);
    }

    /** @return list<array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
