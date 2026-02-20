<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Misc;

use LukaLtaApi\Exception\ValueException;
use ReflectionClass;
use ReflectionClassConstant;
use Stringable;

class AppEnv implements Stringable
{
    public const string ENV_DEVELOPMENT = 'development';
    public const string ENV_PRODUCTION  = 'production';

    /**
     * @throws ValueException
     */
    private function __construct(
        private readonly string $value,
    ) {
        $valid = self::getValidValues();
        if (!in_array($value, $valid)) {
            throw new ValueException(sprintf(
                'AppEnv "%s" is invalid',
                $value,
            ));
        }
    }

    /**
     * @throws ValueException If the given value is not enumerated as a const
     */
    public static function create(
        string $appEnv
    ): self {
        return new self($appEnv);
    }

    /**
     * @return string[]
     */
    public static function getValidValues(): array
    {
        $reflection = new ReflectionClass(self::class);
        return $reflection->getConstants(ReflectionClassConstant::IS_PUBLIC);
    }

    /**
     * @param string $envToCheck One of AppEnv::ENV_*
     *
     * @SuppressWarnings(PHPMD.ShortMethodName)
     */
    public function matches(
        string $envToCheck,
    ): bool {
        return $this->value === $envToCheck;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function isDevelopment(): bool
    {
        return $this->value === self::ENV_DEVELOPMENT;
    }

    public function isProduction(): bool
    {
        return $this->value === self::ENV_PRODUCTION;
    }
}
