<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Preview;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class PreviewTokens implements IteratorAggregate, JsonSerializable, Countable
{
    private readonly array $tokens;

    private function __construct(
        PreviewToken ...$tokens
    ) {
        $this->tokens = $tokens;
    }

    public static function from(PreviewToken ...$tokens): self
    {
        return new self(...$tokens);
    }

    public function toArray(): array
    {
        return array_map(static fn($token) => $token->toArray(), $this->tokens);
    }

    public function getIterator(): Generator
    {
        yield from $this->tokens;
    }

    public function count(): int
    {
        return count($this->tokens);
    }

    public function jsonSerialize(): array
    {
        return $this->tokens;
    }
}
