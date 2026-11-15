<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use JsonSerializable;
use LukaLtaApi\Exception\ApiValidationException;

/**
 * Verweis eines kuratierten Topologie-Knotens auf Live-Daten des Agents. Ein Knoten ohne
 * Verweis (Internet, Cloudflare, Domain …) hat `none` und einen manuell gepflegten Status.
 */
final class NodeReference implements JsonSerializable
{
    public const string TYPE_NONE    = 'none';
    public const string TYPE_HOST    = 'host';
    public const string TYPE_SERVICE = 'service';

    private function __construct(
        private readonly string      $type,
        private readonly ?HostId     $hostId,
        private readonly ?ServiceKey $serviceKey,
    ) {
    }

    public static function none(): self
    {
        return new self(self::TYPE_NONE, null, null);
    }

    public static function host(HostId $hostId): self
    {
        return new self(self::TYPE_HOST, $hostId, null);
    }

    public static function service(HostId $hostId, ServiceKey $serviceKey): self
    {
        return new self(self::TYPE_SERVICE, $hostId, $serviceKey);
    }

    /**
     * Parst `{type: 'host', hostId}` bzw. `{type: 'service', hostId, serviceKey}`; null = kein Verweis.
     */
    public static function fromArray(?array $data): self
    {
        if ($data === null) {
            return self::none();
        }

        $type = $data['type'] ?? self::TYPE_NONE;

        return match ($type) {
            self::TYPE_NONE    => self::none(),
            self::TYPE_HOST    => self::host(HostId::fromString((string) ($data['hostId'] ?? ''))),
            self::TYPE_SERVICE => self::service(
                HostId::fromString((string) ($data['hostId'] ?? '')),
                ServiceKey::fromString((string) ($data['serviceKey'] ?? '')),
            ),
            default => throw new ApiValidationException('Invalid node reference type', 400),
        };
    }

    public static function fromDatabase(string $type, ?string $hostId, ?string $serviceKey): self
    {
        return match ($type) {
            self::TYPE_HOST    => self::host(HostId::fromString((string) $hostId)),
            self::TYPE_SERVICE => self::service(
                HostId::fromString((string) $hostId),
                ServiceKey::fromString((string) $serviceKey),
            ),
            default => self::none(),
        };
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getHostId(): ?HostId
    {
        return $this->hostId;
    }

    public function getServiceKey(): ?ServiceKey
    {
        return $this->serviceKey;
    }

    public function isNone(): bool
    {
        return $this->type === self::TYPE_NONE;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type
            && $this->hostId?->asString() === $other->hostId?->asString()
            && $this->serviceKey?->asString() === $other->serviceKey?->asString();
    }

    public function jsonSerialize(): ?array
    {
        if ($this->isNone()) {
            return null;
        }

        return [
            'type'       => $this->type,
            'hostId'     => $this->hostId?->asString(),
            'serviceKey' => $this->serviceKey?->asString(),
        ];
    }
}
