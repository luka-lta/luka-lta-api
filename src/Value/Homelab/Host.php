<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use DateTimeImmutable;
use JsonSerializable;

class Host implements JsonSerializable
{
    private function __construct(
        private readonly string             $hostId,
        private readonly string             $name,
        private readonly string             $nodeType,
        private readonly float              $cpuUsagePercent,
        private readonly float              $memoryUsedGb,
        private readonly float              $memoryTotalGb,
        private readonly float              $diskUsedGb,
        private readonly float              $diskTotalGb,
        private readonly float              $loadAverage1,
        private readonly float              $loadAverage5,
        private readonly float              $loadAverage15,
        private readonly ?float             $temperatureCelsius,
        private readonly int                $uptimeSeconds,
        private readonly DateTimeImmutable   $updatedAt,
    ) {
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            $row['host_id'],
            $row['name'],
            $row['node_type'],
            (float) $row['cpu_usage_percent'],
            (float) $row['memory_used_gb'],
            (float) $row['memory_total_gb'],
            (float) $row['disk_used_gb'],
            (float) $row['disk_total_gb'],
            (float) $row['load_average_1'],
            (float) $row['load_average_5'],
            (float) $row['load_average_15'],
            $row['temperature_celsius'] !== null ? (float) $row['temperature_celsius'] : null,
            (int) $row['uptime_seconds'],
            new DateTimeImmutable($row['updated_at']),
        );
    }

    public function getHostId(): string
    {
        return $this->hostId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNodeType(): string
    {
        return $this->nodeType;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getMemoryUsagePercent(): float
    {
        if ($this->memoryTotalGb <= 0.0) {
            return 0.0;
        }

        return round($this->memoryUsedGb / $this->memoryTotalGb * 100, 1);
    }

    public function getDiskUsagePercent(): float
    {
        if ($this->diskTotalGb <= 0.0) {
            return 0.0;
        }

        return round($this->diskUsedGb / $this->diskTotalGb * 100, 1);
    }

    public function jsonSerialize(): array
    {
        return [
            'id'                  => $this->hostId,
            'name'                => $this->name,
            'nodeType'            => $this->nodeType,
            'cpuUsagePercent'     => $this->cpuUsagePercent,
            'memoryUsagePercent'  => $this->getMemoryUsagePercent(),
            'memoryUsedGb'        => $this->memoryUsedGb,
            'memoryTotalGb'       => $this->memoryTotalGb,
            'diskUsagePercent'    => $this->getDiskUsagePercent(),
            'diskUsedGb'          => $this->diskUsedGb,
            'diskTotalGb'         => $this->diskTotalGb,
            'loadAverage'         => [$this->loadAverage1, $this->loadAverage5, $this->loadAverage15],
            'temperatureCelsius'  => $this->temperatureCelsius,
            'uptimeSeconds'       => $this->uptimeSeconds,
            'updatedAt'           => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
