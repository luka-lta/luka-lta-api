<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Blog\Tag;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class Tags implements IteratorAggregate, JsonSerializable, Countable
{
    private readonly array $tags;

    private function __construct(Tag ...$tags)
    {
        $this->tags = $tags;
    }

    public static function from(Tag ...$tags): self
    {
        return new self(...$tags);
    }

    public static function empty(): self
    {
        return new self();
    }

    public function getIterator(): Generator
    {
        yield from $this->tags;
    }

    public function count(): int
    {
        return count($this->tags);
    }

    public function jsonSerialize(): array
    {
        return $this->tags;
    }

    public function toArray(): array
    {
        return array_map(static fn(Tag $tag) => $tag->toArray(), $this->tags);
    }
}
