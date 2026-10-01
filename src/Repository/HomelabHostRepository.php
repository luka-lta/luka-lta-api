<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Homelab\Host;
use LukaLtaApi\Value\Homelab\HostId;
use LukaLtaApi\Value\Homelab\Hosts;
use LukaLtaApi\Value\Homelab\MetricPoint;
use LukaLtaApi\Value\Homelab\MetricSeries;
use PDO;
use PDOException;

class HomelabHostRepository
{
    private const HOST_SELECT = <<<SQL
        SELECT
            host_id,
            name,
            node_type,
            cpu_usage_percent,
            memory_used_gb,
            memory_total_gb,
            disk_used_gb,
            disk_total_gb,
            load_average_1,
            load_average_5,
            load_average_15,
            temperature_celsius,
            uptime_seconds,
            updated_at
        FROM homelab_hosts
    SQL;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function loadAll(): Hosts
    {
        $sql = self::HOST_SELECT . ' ORDER BY name ASC';

        try {
            $stmt = $this->pdo->query($sql);

            $hosts = [];
            foreach ($stmt as $row) {
                $hosts[] = Host::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch hosts.', previous: $exception);
        }

        return Hosts::from(...$hosts);
    }

    public function loadHost(HostId $hostId): ?Host
    {
        $sql = self::HOST_SELECT . ' WHERE host_id = :host_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['host_id' => $hostId->asString()]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch host.', previous: $exception);
        }

        if ($row === false) {
            return null;
        }

        return Host::fromDatabase($row);
    }

    public function loadMetrics(HostId $hostId, string $metricType, int $sinceMinutes): MetricSeries
    {
        $sql = <<<SQL
            SELECT value, recorded_at
            FROM homelab_host_metrics
            WHERE host_id = :host_id
              AND metric_type = :metric_type
              AND recorded_at >= :since
            ORDER BY recorded_at ASC
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'host_id'     => $hostId->asString(),
                'metric_type' => $metricType,
                'since'       => (new \DateTimeImmutable("-{$sinceMinutes} minutes"))->format('Y-m-d H:i:s'),
            ]);

            $points = [];
            foreach ($stmt as $row) {
                $points[] = MetricPoint::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch host metrics.', previous: $exception);
        }

        return MetricSeries::from(...$points);
    }

    public function upsert(HostId $hostId, array $data): void
    {
        $sql = <<<SQL
            INSERT INTO homelab_hosts (
                host_id, name, node_type, cpu_usage_percent, memory_used_gb, memory_total_gb,
                disk_used_gb, disk_total_gb, load_average_1, load_average_5, load_average_15,
                temperature_celsius, uptime_seconds
            ) VALUES (
                :host_id, :name, :node_type, :cpu_usage_percent, :memory_used_gb, :memory_total_gb,
                :disk_used_gb, :disk_total_gb, :load_average_1, :load_average_5, :load_average_15,
                :temperature_celsius, :uptime_seconds
            )
            ON DUPLICATE KEY UPDATE
                name                 = VALUES(name),
                cpu_usage_percent    = VALUES(cpu_usage_percent),
                memory_used_gb       = VALUES(memory_used_gb),
                memory_total_gb      = VALUES(memory_total_gb),
                disk_used_gb         = VALUES(disk_used_gb),
                disk_total_gb        = VALUES(disk_total_gb),
                load_average_1       = VALUES(load_average_1),
                load_average_5       = VALUES(load_average_5),
                load_average_15      = VALUES(load_average_15),
                temperature_celsius  = VALUES(temperature_celsius),
                uptime_seconds       = VALUES(uptime_seconds)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'host_id'             => $hostId->asString(),
                'name'                => $data['name'],
                'node_type'           => $data['nodeType'] ?? 'server',
                'cpu_usage_percent'   => $data['cpuUsagePercent'],
                'memory_used_gb'      => $data['memoryUsedGb'],
                'memory_total_gb'     => $data['memoryTotalGb'],
                'disk_used_gb'        => $data['diskUsedGb'],
                'disk_total_gb'       => $data['diskTotalGb'],
                'load_average_1'      => $data['loadAverage'][0],
                'load_average_5'      => $data['loadAverage'][1],
                'load_average_15'     => $data['loadAverage'][2],
                'temperature_celsius' => $data['temperatureCelsius'],
                'uptime_seconds'      => $data['uptimeSeconds'],
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to store host.', previous: $exception);
        }
    }

    public function updateNodeType(HostId $hostId, string $nodeType): void
    {
        $sql = 'UPDATE homelab_hosts SET node_type = :node_type WHERE host_id = :host_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['node_type' => $nodeType, 'host_id' => $hostId->asString()]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to update host node type.', previous: $exception);
        }
    }

    public function purgeMetricsOlderThan(\DateTimeImmutable $cutoff): int
    {
        $sql = 'DELETE FROM homelab_host_metrics WHERE recorded_at < :cutoff';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s')]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to purge host metrics.', previous: $exception);
        }

        return $stmt->rowCount();
    }

    public function insertMetric(HostId $hostId, string $metricType, float $value, \DateTimeImmutable $recordedAt): void
    {
        $sql = <<<SQL
            INSERT INTO homelab_host_metrics (host_id, metric_type, value, recorded_at)
            VALUES (:host_id, :metric_type, :value, :recorded_at)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'host_id'     => $hostId->asString(),
                'metric_type' => $metricType,
                'value'       => $value,
                'recorded_at' => $recordedAt->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to store host metric.', previous: $exception);
        }
    }
}
