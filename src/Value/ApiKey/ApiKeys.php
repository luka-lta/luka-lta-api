<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\ApiKey;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class ApiKeys implements Countable, IteratorAggregate, JsonSerializable
{
    private readonly array $apiKeys;

    private function __construct(ApiKey ...$apiKeys)
    {
        $this->apiKeys = $apiKeys;
    }

    public static function from(ApiKey ...$apiKeys): self
    {
        return new self(...$apiKeys);
    }

    public function getIterator(): Generator
    {
        yield from $this->apiKeys;
    }

    public function count(): int
    {
        return count($this->apiKeys);
    }

    public function jsonSerialize(): array
    {
        return $this->apiKeys;
    }
}
