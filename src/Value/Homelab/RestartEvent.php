<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use DateTimeImmutable;
use JsonSerializable;

class RestartEvent implements JsonSerializable
{
    private function __construct(
        private readonly DateTimeImmutable $occurredAt,
        private readonly string            $reason,
    ) {
    }

    public static function from(DateTimeImmutable $occurredAt, string $reason): self
    {
        return new self($occurredAt, $reason);
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            new DateTimeImmutable($row['occurred_at']),
            $row['reason'],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'timestamp' => $this->occurredAt->format('Y-m-d H:i:s'),
            'reason'    => $this->reason,
        ];
    }
}
