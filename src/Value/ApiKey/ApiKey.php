<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\ApiKey;

class ApiKey
{
    private function __construct(
        private readonly string $apiKey
    ) {
    }

    public static function from(string $apiKey): self
    {
        return new self($apiKey);
    }

    public static function generateApiKey(): self
    {
        $randomBytes = random_bytes(32);
        return new self(bin2hex($randomBytes));
    }

    public function __toString(): string
    {
        return $this->apiKey;
    }
}
