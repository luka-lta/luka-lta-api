<?php

namespace LukaLtaApi\Value\Stats;

abstract class AbstractStat
{
    private function __construct(
        private readonly string $label,
        private readonly string $labelValue,
        private readonly int $amount,
        private readonly int $percentage,
    ) {
    }

    public static function from(
        string $label,
        string $labelValue,
        int $amount,
        int $percentage,
    ): static {
        return new static($label, $labelValue, $amount, $percentage);
    }

    abstract protected function getLabelKey(): string;

    public function toArray(): array
    {
        return [
            $this->getLabelKey() => $this->labelValue,
            'amount' => $this->amount,
            'percentage' => $this->percentage,
        ];
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getLabelValue(): string
    {
        return $this->labelValue;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getPercentage(): int
    {
        return $this->percentage;
    }
}
