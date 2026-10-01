<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use DateTimeImmutable;
use JsonSerializable;

class MetricPoint implements JsonSerializable
{
    private function __construct(
        private readonly DateTimeImmutable $timestamp,
        private readonly float             $value,
    ) {
    }

    public static function from(DateTimeImmutable $timestamp, float $value): self
    {
        return new self($timestamp, $value);
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            new DateTimeImmutable($row['recorded_at']),
            (float) $row['value'],
        );
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function jsonSerialize(): array
    {
        return [
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'value'     => $this->value,
        ];
    }
}
