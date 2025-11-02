<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Tracking;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class Clicks implements Countable, IteratorAggregate, JsonSerializable
{
    private readonly array $clicks;

    private function __construct(Click ...$clicks)
    {
        $this->clicks = $clicks;
    }

    public static function from(Click ...$click): self
    {
        return new self(...$click);
    }

    public function getIterator(): Generator
    {
        yield from $this->clicks;
    }

    public function count(): int
    {
        return count($this->clicks);
    }

    public function toArray(): array
    {
        return array_map(static fn($click) => $click->toArray(), $this->clicks);
    }

    public function toFrontend(): array
    {
        $clicks = [];

        foreach ($this->clicks as $click) {
            $clicks[] = [
                'clickId' => $click->getClickId()?->asInt(),
                'clickTag' => $click->getTag()->getValue(),
                'url' => (string)$click->getUrl(),
                'clickedAt' => $click->getClickedAt()?->format('Y-m-d H:i:s'),
                'ipAddress' => $click->getIpAddress(),
                'market' => $click->getMarket(),
                'userAgent' => $click->getUserAgent(),
                'referer' => $click->getReferer(),
            ];
        }

        return $clicks;
    }

    public function jsonSerialize(): array
    {
        return $this->clicks;
    }
}
