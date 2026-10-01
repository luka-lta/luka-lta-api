<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use DateTimeImmutable;
use JsonSerializable;

class HealthCheckEvent implements JsonSerializable
{
    private function __construct(
        private readonly DateTimeImmutable $checkedAt,
        private readonly string            $status,
        private readonly string            $message,
    ) {
    }

    public static function from(DateTimeImmutable $checkedAt, string $status, string $message): self
    {
        return new self($checkedAt, $status, $message);
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            new DateTimeImmutable($row['checked_at']),
            $row['status'],
            $row['message'],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'timestamp' => $this->checkedAt->format('Y-m-d H:i:s'),
            'status'    => $this->status,
            'message'   => $this->message,
        ];
    }
}
