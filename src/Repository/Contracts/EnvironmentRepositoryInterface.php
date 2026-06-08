<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository\Contracts;

interface EnvironmentRepositoryInterface
{
    public function getEnvironmentVariable(string $name, mixed $default = null, bool $defaultIsNull = false): ?string;

    public function get(string $name, mixed $default = null, bool $defaultIsNull = false): ?string;

    public function getEnvVars(string ...$queriedEnvVars): array;
}
