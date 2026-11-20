<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use Countable;
use Generator;
use IteratorAggregate;

class Customers implements IteratorAggregate, Countable
{
    /** @var list<Customer> */
    private readonly array $customers;

    private function __construct(Customer ...$customers)
    {
        $this->customers = array_values($customers);
    }

    public static function from(Customer ...$customers): self
    {
        return new self(...$customers);
    }

    public static function empty(): self
    {
        return new self();
    }

    public function getIterator(): Generator
    {
        yield from $this->customers;
    }

    public function count(): int
    {
        return count($this->customers);
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(static fn(Customer $customer) => $customer->toArray(), $this->customers);
    }
}
