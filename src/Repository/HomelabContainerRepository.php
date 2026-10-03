<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use DateTimeImmutable;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Homelab\Container;
use LukaLtaApi\Value\Homelab\ContainerId;
use LukaLtaApi\Value\Homelab\Containers;
use LukaLtaApi\Value\Homelab\HealthCheckEvent;
use LukaLtaApi\Value\Homelab\HostId;
use LukaLtaApi\Value\Homelab\LogLine;
use LukaLtaApi\Value\Homelab\MetricPoint;
use LukaLtaApi\Value\Homelab\MetricSeries;
use LukaLtaApi\Value\Homelab\RestartEvent;
use PDO;
use PDOException;

class HomelabContainerRepository
{
    private const CONTAINER_SELECT = <<<SQL
        SELECT
            container_id,
            host_id,
            name,
            image,
            status,
            health_status,
            started_at,
            cpu_usage_percent,
            memory_used_mb,
            memory_limit_mb,
            network_in_mbps,
            network_out_mbps,
            restart_count,
            last_health_check_at,
            ports,
            volumes,
            environment,
            networks,
            labels,
            node_role,
            updated_at
        FROM homelab_containers
    SQL;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function loadAll(): Containers
    {
        $sql = self::CONTAINER_SELECT . ' ORDER BY name ASC';

        try {
            $stmt = $this->pdo->query($sql);

            $containers = [];
            foreach ($stmt as $row) {
                $containers[] = Container::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch containers.', previous: $exception);
        }

        return Containers::from(...$containers);
    }

    public function loadByHost(HostId $hostId): Containers
    {
        $sql = self::CONTAINER_SELECT . ' WHERE host_id = :host_id ORDER BY name ASC';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['host_id' => $hostId->asString()]);

            $containers = [];
            foreach ($stmt as $row) {
                $containers[] = Container::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch containers for host.', previous: $exception);
        }

        return Containers::from(...$containers);
    }

    public function loadContainer(ContainerId $containerId): ?Container
    {
        $sql = self::CONTAINER_SELECT . ' WHERE container_id = :container_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['container_id' => $containerId->asString()]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch container.', previous: $exception);
        }

        if ($row === false) {
            return null;
        }

        return Container::fromDatabase($row);
    }

    public function loadMetrics(ContainerId $containerId, string $metricType, int $sinceMinutes): MetricSeries
    {
        $sql = <<<SQL
            SELECT value, recorded_at
            FROM homelab_container_metrics
            WHERE container_id = :container_id
              AND metric_type = :metric_type
              AND recorded_at >= :since
            ORDER BY recorded_at ASC
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'container_id' => $containerId->asString(),
                'metric_type'  => $metricType,
                'since'        => (new DateTimeImmutable("-{$sinceMinutes} minutes"))->format('Y-m-d H:i:s'),
            ]);

            $points = [];
            foreach ($stmt as $row) {
                $points[] = MetricPoint::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch container metrics.', previous: $exception);
        }

        return MetricSeries::from(...$points);
    }

    public function loadRestartHistory(ContainerId $containerId, int $limit = 50): array
    {
        $sql = <<<SQL
            SELECT reason, occurred_at
            FROM homelab_container_restarts
            WHERE container_id = :container_id
            ORDER BY occurred_at DESC
            LIMIT :limit
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue('container_id', $containerId->asString(), PDO::PARAM_STR);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $events = [];
            foreach ($stmt as $row) {
                $events[] = RestartEvent::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch restart history.', previous: $exception);
        }

        return $events;
    }

    public function loadHealthCheckHistory(ContainerId $containerId, int $limit = 50): array
    {
        $sql = <<<SQL
            SELECT status, message, checked_at
            FROM homelab_container_health_checks
            WHERE container_id = :container_id
            ORDER BY checked_at DESC
            LIMIT :limit
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue('container_id', $containerId->asString(), PDO::PARAM_STR);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $events = [];
            foreach ($stmt as $row) {
                $events[] = HealthCheckEvent::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch health check history.', previous: $exception);
        }

        return $events;
    }

    public function loadLogs(ContainerId $containerId, int $limit = 100): array
    {
        $sql = <<<SQL
            SELECT level, message, logged_at
            FROM homelab_container_logs
            WHERE container_id = :container_id
            ORDER BY logged_at DESC
            LIMIT :limit
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue('container_id', $containerId->asString(), PDO::PARAM_STR);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $logs = [];
            foreach ($stmt as $row) {
                $logs[] = LogLine::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch container logs.', previous: $exception);
        }

        return $logs;
    }

    public function upsert(ContainerId $containerId, HostId $hostId, array $data): void
    {
        $sql = <<<SQL
            INSERT INTO homelab_containers (
                container_id, host_id, name, image, status, health_status, started_at,
                cpu_usage_percent, memory_used_mb, memory_limit_mb, network_in_mbps, network_out_mbps,
                restart_count, last_health_check_at, ports, volumes, environment, networks, labels, node_role
            ) VALUES (
                :container_id, :host_id, :name, :image, :status, :health_status, :started_at,
                :cpu_usage_percent, :memory_used_mb, :memory_limit_mb, :network_in_mbps, :network_out_mbps,
                :restart_count, :last_health_check_at, :ports, :volumes, :environment, :networks, :labels, :node_role
            )
            ON DUPLICATE KEY UPDATE
                host_id               = VALUES(host_id),
                name                  = VALUES(name),
                image                 = VALUES(image),
                status                = VALUES(status),
                health_status         = VALUES(health_status),
                started_at            = VALUES(started_at),
                cpu_usage_percent     = VALUES(cpu_usage_percent),
                memory_used_mb        = VALUES(memory_used_mb),
                memory_limit_mb       = VALUES(memory_limit_mb),
                network_in_mbps       = VALUES(network_in_mbps),
                network_out_mbps      = VALUES(network_out_mbps),
                restart_count         = VALUES(restart_count),
                last_health_check_at  = VALUES(last_health_check_at),
                ports                 = VALUES(ports),
                volumes               = VALUES(volumes),
                environment           = VALUES(environment),
                networks              = VALUES(networks),
                labels                = VALUES(labels)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'container_id'         => $containerId->asString(),
                'host_id'              => $hostId->asString(),
                'name'                 => $data['name'],
                'image'                => $data['image'],
                'status'               => $data['status'],
                'health_status'        => $data['healthStatus'],
                'started_at'           => $data['startedAt'],
                'cpu_usage_percent'    => $data['cpuUsagePercent'],
                'memory_used_mb'       => $data['memoryUsedMb'],
                'memory_limit_mb'      => $data['memoryLimitMb'],
                'network_in_mbps'      => $data['networkInMbps'],
                'network_out_mbps'     => $data['networkOutMbps'],
                'restart_count'        => $data['restartCount'],
                'last_health_check_at' => $data['lastHealthCheckAt'],
                'ports'                => json_encode($data['ports'] ?? [], JSON_THROW_ON_ERROR),
                'volumes'              => json_encode($data['volumes'] ?? [], JSON_THROW_ON_ERROR),
                'environment'          => json_encode($data['environment'] ?? [], JSON_THROW_ON_ERROR),
                'networks'             => json_encode($data['networks'] ?? [], JSON_THROW_ON_ERROR),
                'labels'               => json_encode($data['labels'] ?? [], JSON_THROW_ON_ERROR),
                'node_role'            => $data['nodeRole'] ?? null,
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to store container.', previous: $exception);
        }
    }

    public function updateNodeRole(ContainerId $containerId, ?string $nodeRole): void
    {
        $sql = 'UPDATE homelab_containers SET node_role = :node_role WHERE container_id = :container_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['node_role' => $nodeRole, 'container_id' => $containerId->asString()]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to update container node role.', previous: $exception);
        }
    }

    /**
     * Deletes any container stored for this host but absent from $presentContainerIds.
     * The agent reports every container `docker ps -a` still sees, running or stopped —
     * so a container missing from that list was actually removed (`docker rm`), not
     * merely stopped, and must not be kept around as a stale row.
     *
     * @param string[] $presentContainerIds
     */
    public function deleteMissing(HostId $hostId, array $presentContainerIds): int
    {
        if (empty($presentContainerIds)) {
            $sql = 'DELETE FROM homelab_containers WHERE host_id = :host_id';

            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['host_id' => $hostId->asString()]);
            } catch (PDOException $exception) {
                throw new ApiDatabaseException('Failed to delete removed containers.', previous: $exception);
            }

            return $stmt->rowCount();
        }

        $placeholders = [];
        $params       = ['host_id' => $hostId->asString()];
        foreach (array_values($presentContainerIds) as $i => $containerId) {
            $placeholder                = "present_{$i}";
            $placeholders[]             = ":{$placeholder}";
            $params[$placeholder]       = $containerId;
        }

        $placeholderList = implode(',', $placeholders);
        $sql             = <<<SQL
            DELETE FROM homelab_containers
            WHERE host_id = :host_id
              AND container_id NOT IN ({$placeholderList})
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to delete removed containers.', previous: $exception);
        }

        return $stmt->rowCount();
    }

    public function purgeMetricsOlderThan(DateTimeImmutable $cutoff): int
    {
        return $this->purgeOlderThan('homelab_container_metrics', 'recorded_at', $cutoff);
    }

    public function purgeRestartsOlderThan(DateTimeImmutable $cutoff): int
    {
        return $this->purgeOlderThan('homelab_container_restarts', 'occurred_at', $cutoff);
    }

    public function purgeHealthChecksOlderThan(DateTimeImmutable $cutoff): int
    {
        return $this->purgeOlderThan('homelab_container_health_checks', 'checked_at', $cutoff);
    }

    public function purgeLogsOlderThan(DateTimeImmutable $cutoff): int
    {
        return $this->purgeOlderThan('homelab_container_logs', 'logged_at', $cutoff);
    }

    private function purgeOlderThan(string $table, string $column, DateTimeImmutable $cutoff): int
    {
        $sql = "DELETE FROM {$table} WHERE {$column} < :cutoff";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s')]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException("Failed to purge {$table}.", previous: $exception);
        }

        return $stmt->rowCount();
    }

    public function insertMetric(
        ContainerId $containerId,
        string $metricType,
        float $value,
        DateTimeImmutable $recordedAt,
    ): void {
        $sql = <<<SQL
            INSERT INTO homelab_container_metrics (container_id, metric_type, value, recorded_at)
            VALUES (:container_id, :metric_type, :value, :recorded_at)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'container_id' => $containerId->asString(),
                'metric_type'  => $metricType,
                'value'        => $value,
                'recorded_at'  => $recordedAt->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to store container metric.', previous: $exception);
        }
    }

    public function insertRestartEvent(ContainerId $containerId, string $reason, DateTimeImmutable $occurredAt): void
    {
        $sql = <<<SQL
            INSERT INTO homelab_container_restarts (container_id, reason, occurred_at)
            VALUES (:container_id, :reason, :occurred_at)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'container_id' => $containerId->asString(),
                'reason'       => $reason,
                'occurred_at'  => $occurredAt->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to store restart event.', previous: $exception);
        }
    }

    public function insertHealthCheckEvent(
        ContainerId $containerId,
        string $status,
        string $message,
        DateTimeImmutable $checkedAt,
    ): void {
        $sql = <<<SQL
            INSERT INTO homelab_container_health_checks (container_id, status, message, checked_at)
            VALUES (:container_id, :status, :message, :checked_at)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'container_id' => $containerId->asString(),
                'status'       => $status,
                'message'      => $message,
                'checked_at'   => $checkedAt->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to store health check event.', previous: $exception);
        }
    }

    public function insertLogLine(
        ContainerId $containerId,
        string $level,
        string $message,
        DateTimeImmutable $loggedAt,
    ): void {
        $sql = <<<SQL
            INSERT INTO homelab_container_logs (container_id, level, message, logged_at)
            VALUES (:container_id, :level, :message, :logged_at)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'container_id' => $containerId->asString(),
                'level'        => $level,
                'message'      => $message,
                'logged_at'    => $loggedAt->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to store log line.', previous: $exception);
        }
    }
}
