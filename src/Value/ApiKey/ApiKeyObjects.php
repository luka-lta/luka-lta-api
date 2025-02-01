<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\ApiKey;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class ApiKeyObjects implements IteratorAggregate, JsonSerializable, Countable
{
    private readonly array $apiKeys;

    private function __construct(
        ApiKeyObject ...$apiKeys
    ) {
        $this->apiKeys = $apiKeys;
    }

    public static function from(ApiKeyObject ...$apiKeys): self
    {
        return new self(...$apiKeys);
    }

    public function toArray(): array
    {
        return array_map(static fn($apiKey) => $apiKey->toArray(), $this->apiKeys);
    }

    public function getIterator(): Generator
    {
        yield from $this->apiKeys;
    }

    public function jsonSerialize(): array
    {
        return $this->apiKeys;
    }

    public function count(): int
    {
        return count($this->apiKeys);
    }
}
