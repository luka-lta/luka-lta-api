<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Homelab;

use DateTimeImmutable;
use JsonSerializable;

class Container implements JsonSerializable
{
    private MetricSeries $cpuHistory;
    private MetricSeries $memoryHistory;

    /** @var RestartEvent[] */
    private array $restartHistory = [];

    /** @var HealthCheckEvent[] */
    private array $healthCheckHistory = [];

    /** @var LogLine[] */
    private array $logs = [];

    private function __construct(
        private readonly string             $containerId,
        private readonly string             $hostId,
        private readonly string             $name,
        private readonly string             $image,
        private readonly string             $status,
        private readonly string             $healthStatus,
        private readonly ?DateTimeImmutable $startedAt,
        private readonly float              $cpuUsagePercent,
        private readonly float              $memoryUsedMb,
        private readonly float              $memoryLimitMb,
        private readonly float              $networkInMbps,
        private readonly float              $networkOutMbps,
        private readonly int                $restartCount,
        private readonly ?DateTimeImmutable $lastHealthCheckAt,
        private readonly array              $ports,
        private readonly array              $volumes,
        private readonly array              $environment,
        private readonly array              $networks,
        private readonly array              $labels,
        private readonly ?string            $nodeRole,
        private readonly DateTimeImmutable   $updatedAt,
    ) {
        $this->cpuHistory    = MetricSeries::from();
        $this->memoryHistory = MetricSeries::from();
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            $row['container_id'],
            $row['host_id'],
            $row['name'],
            $row['image'],
            $row['status'],
            $row['health_status'],
            $row['started_at'] !== null ? new DateTimeImmutable($row['started_at']) : null,
            (float) $row['cpu_usage_percent'],
            (float) $row['memory_used_mb'],
            (float) $row['memory_limit_mb'],
            (float) $row['network_in_mbps'],
            (float) $row['network_out_mbps'],
            (int) $row['restart_count'],
            $row['last_health_check_at'] !== null ? new DateTimeImmutable($row['last_health_check_at']) : null,
            $row['ports'] !== null ? json_decode($row['ports'], true) : [],
            $row['volumes'] !== null ? json_decode($row['volumes'], true) : [],
            $row['environment'] !== null ? json_decode($row['environment'], true) : [],
            $row['networks'] !== null ? json_decode($row['networks'], true) : [],
            $row['labels'] !== null ? json_decode($row['labels'], true) : [],
            $row['node_role'] ?? null,
            new DateTimeImmutable($row['updated_at']),
        );
    }

    public function getContainerId(): string
    {
        return $this->containerId;
    }

    public function getHostId(): string
    {
        return $this->hostId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getNetworks(): array
    {
        return $this->networks;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }

    public function getNodeRole(): ?string
    {
        return $this->nodeRole;
    }

    public function setCpuHistory(MetricSeries $cpuHistory): void
    {
        $this->cpuHistory = $cpuHistory;
    }

    public function setMemoryHistory(MetricSeries $memoryHistory): void
    {
        $this->memoryHistory = $memoryHistory;
    }

    /** @param RestartEvent[] $restartHistory */
    public function setRestartHistory(array $restartHistory): void
    {
        $this->restartHistory = $restartHistory;
    }

    /** @param HealthCheckEvent[] $healthCheckHistory */
    public function setHealthCheckHistory(array $healthCheckHistory): void
    {
        $this->healthCheckHistory = $healthCheckHistory;
    }

    /** @param LogLine[] $logs */
    public function setLogs(array $logs): void
    {
        $this->logs = $logs;
    }

    private function getUptimeSeconds(): ?int
    {
        if ($this->startedAt === null) {
            return null;
        }

        return (new DateTimeImmutable())->getTimestamp() - $this->startedAt->getTimestamp();
    }

    public function jsonSerialize(): array
    {
        return [
            'id'                 => $this->containerId,
            'name'               => $this->name,
            'image'              => $this->image,
            'status'             => $this->status,
            'healthStatus'       => $this->healthStatus,
            'hostId'             => $this->hostId,
            'uptimeSeconds'      => $this->getUptimeSeconds(),
            'cpuUsagePercent'    => $this->cpuUsagePercent,
            'memoryUsedMb'       => $this->memoryUsedMb,
            'memoryLimitMb'      => $this->memoryLimitMb,
            'networkInMbps'      => $this->networkInMbps,
            'networkOutMbps'     => $this->networkOutMbps,
            'restartCount'       => $this->restartCount,
            'lastHealthCheck'    => $this->lastHealthCheckAt?->format('Y-m-d H:i:s'),
            'ports'              => $this->ports,
            'volumes'            => $this->volumes,
            'environment'        => (object) $this->environment,
            'networks'           => $this->networks,
            'labels'             => (object) $this->labels,
            'nodeRole'           => $this->nodeRole,
            'cpuHistory'         => $this->cpuHistory,
            'memoryHistory'      => $this->memoryHistory,
            'restartHistory'     => $this->restartHistory,
            'healthCheckHistory' => $this->healthCheckHistory,
            'logs'               => $this->logs,
        ];
    }
}
