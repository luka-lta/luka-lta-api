<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Tracking;

class ClickTag
{
    public function __construct(
        private readonly string $value
    ) {
    }

    public static function generateTag(): self
    {
        $uniqueCode = strtoupper(bin2hex(random_bytes(8)));
        return new self($uniqueCode);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getAsTracking(): string
    {
        return 'https://luka-lta.dev/redirect/' . $this->value;
    }
}
