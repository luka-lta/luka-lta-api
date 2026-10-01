<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use DateTimeImmutable;
use JsonSerializable;

class LogLine implements JsonSerializable
{
    private function __construct(
        private readonly DateTimeImmutable $loggedAt,
        private readonly string            $level,
        private readonly string            $message,
    ) {
    }

    public static function from(DateTimeImmutable $loggedAt, string $level, string $message): self
    {
        return new self($loggedAt, $level, $message);
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            new DateTimeImmutable($row['logged_at']),
            $row['level'],
            $row['message'],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'timestamp' => $this->loggedAt->format('Y-m-d H:i:s'),
            'level'     => $this->level,
            'message'   => $this->message,
        ];
    }
}
