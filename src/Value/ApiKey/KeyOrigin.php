<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\ApiKey;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\InvalidOriginException;

class KeyOrigin
{
    private function __construct(
        private readonly string $origin,
    ) {
        if (filter_input(FILTER_VALIDATE_URL, $origin) === false) {
            throw new InvalidOriginException('Invalid origin', StatusCodeInterface::STATUS_BAD_REQUEST);
        }
    }

    public static function fromString(string $origin): self
    {
        return new self($origin);
    }

    public function __toString(): string
    {
        return $this->origin;
    }
}
