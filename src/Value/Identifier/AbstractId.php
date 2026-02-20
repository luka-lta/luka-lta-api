<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Identifier;

use Random\RandomException;

abstract class AbstractId
{
    protected const int LENGTH = 8;
    private const string CHARSET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    private function __construct(
        private readonly string $id,
    ) {}

    /**
     * @throws RandomException
     */
    public static function fromRandom(): static
    {
        $result = '';
        $randomBytes = random_bytes(static::LENGTH);
        $charsetLength = strlen(self::CHARSET);

        for ($i = 0; $i < static::LENGTH; $i++) {
            $randomIndex = ord($randomBytes[$i]) % $charsetLength;
            $result .= self::CHARSET[$randomIndex];
        }

        return new static($result);
    }

    public static function from(string $id): static
    {
        return new static($id);
    }

    public function asString(): string
    {
        return $this->id;
    }
}
