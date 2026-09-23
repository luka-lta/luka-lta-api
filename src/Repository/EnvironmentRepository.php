<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\UnsetEnvironmentVariableException;

class EnvironmentRepository
{
    public function getEnvironmentVariable(string $name, $default = null, bool $defaultIsNull = false): ?string
    {
        $value = getenv($name);

        if ($value === false) {
            if ($default !== null || $defaultIsNull) {
                return $default;
            }

            throw new UnsetEnvironmentVariableException(sprintf(
                'Environment variable "%s" is not set',
                $name,
            ));
        }

        return $value;
    }

    /**
     * @throws UnsetEnvironmentVariableException
     */
    public function get(string $name, $default = null, bool $defaultIsNull = false): ?string
    {
        return $this->getEnvironmentVariable($name, $default, $defaultIsNull);
    }

    /**
     * @return String[]
     */
    public function getEnvVars(string ...$queriedEnvVars): array
    {
        foreach ($queriedEnvVars as $queriedVariable) {
            $envVars[] = $this->getEnvironmentVariable($queriedVariable, null, true);
        }

        return $envVars ?? [];
    }
}
