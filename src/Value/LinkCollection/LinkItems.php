<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\LinkCollection;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class LinkItems implements Countable, IteratorAggregate, JsonSerializable
{
    private readonly array $links;

    public function __construct(LinkItem ...$linkItem)
    {
        $this->links = $linkItem;
    }

    public static function from(LinkItem ...$linkItem): self
    {
        return new self(...$linkItem);
    }

    public function getIterator(): Generator
    {
        yield from $this->links;
    }

    public function count(): int
    {
        return count($this->links);
    }

    public function toArray(bool $mustRef = false): array
    {
        return array_map(static fn($link) => $link->toArray($mustRef), $this->links);
    }

    public function jsonSerialize(): array
    {
        return $this->links;
    }
}
