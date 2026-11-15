<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use JsonSerializable;
use LukaLtaApi\Exception\ApiValidationException;
use Stringable;

/**
 * Stabile Identität eines Containers über Redeploys hinweg. Docker-IDs ändern sich bei jedem
 * Recreate, `compose-projekt::service` nicht. Ohne Compose-Labels dient der Containername.
 * Eindeutig nur zusammen mit der Host-ID.
 */
final class ServiceKey implements JsonSerializable, Stringable
{
    /** Spaltenbreite von `service_key` bzw. `ref_service_key` */
    private const int MAX_LENGTH = 191;

    private function __construct(
        private readonly string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);
        if ($value === '') {
            throw new ApiValidationException('Service key cannot be empty', 400);
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new ApiValidationException('Service key is too long', 400);
        }

        return new self($value);
    }

    public static function fromLabels(array $labels, string $containerName): self
    {
        $project = $labels['com.docker.compose.project'] ?? null;
        $service = $labels['com.docker.compose.service'] ?? null;

        if (is_string($project) && $project !== '' && is_string($service) && $service !== '') {
            return self::fromString("{$project}::{$service}");
        }

        return self::fromString(ltrim($containerName, '/'));
    }

    public function asString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
