<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Tracking;

class ClickSummary
{
    private function __construct(
        private readonly int $totalClicks,
        private readonly array $clicksMonthly,
        private readonly array $clicksDaily,
    ) {
    }

    public static function from(int $totalClicks, array $clicksMonthly, array $clicksDaily): self
    {
        return new self($totalClicks, $clicksMonthly, $clicksDaily);
    }

    public function toArray(): array
    {
        return [
            'totalClicks' => $this->totalClicks,
            'clicksMonthly' => $this->clicksMonthly,
            'clicksDaily' => $this->clicksDaily,
        ];
    }

    public function getTotalClicks(): int
    {
        return $this->totalClicks;
    }

    public function getClicksMonthly(): array
    {
        return $this->clicksMonthly;
    }

    public function getClicksDaily(): array
    {
        return $this->clicksDaily;
    }
}
