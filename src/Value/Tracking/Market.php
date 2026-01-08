<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Tracking;

class Market
{
    private function __construct(
        private readonly string $market,
    ) {
    }

    public static function from(string $market): self
    {
        return new self($market);
    }

    public function getMarket(): string
    {
        return $this->market;
    }
}
