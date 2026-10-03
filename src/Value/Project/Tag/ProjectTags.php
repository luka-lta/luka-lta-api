<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project\Tag;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class ProjectTags implements IteratorAggregate, JsonSerializable, Countable
{
    private readonly array $tags;

    private function __construct(ProjectTag ...$tags)
    {
        $this->tags = $tags;
    }

    public static function from(ProjectTag ...$tags): self
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
        return $this->toArray();
    }

    public function toArray(): array
    {
        return array_map(static fn(ProjectTag $tag) => $tag->toArray(), $this->tags);
    }
}
