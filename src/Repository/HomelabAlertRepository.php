<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Homelab\Alert;
use LukaLtaApi\Value\Homelab\Alerts;
use PDO;
use PDOException;

class HomelabAlertRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function loadActive(int $limit = 50): Alerts
    {
        $sql = <<<SQL
            SELECT alert_id, severity, title, description, container_id, host_id, created_at
            FROM homelab_alerts
            WHERE resolved_at IS NULL
            ORDER BY created_at DESC
            LIMIT :limit
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $alerts = [];
            foreach ($stmt as $row) {
                $alerts[] = Alert::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch alerts.', previous: $exception);
        }

        return Alerts::from(...$alerts);
    }

    public function hasActiveAlert(string $title, ?string $containerId, ?string $hostId): bool
    {
        $sql = <<<SQL
            SELECT alert_id
            FROM homelab_alerts
            WHERE resolved_at IS NULL
              AND title = :title
              AND container_id <=> :container_id
              AND host_id <=> :host_id
            LIMIT 1
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'title'        => $title,
                'container_id' => $containerId,
                'host_id'      => $hostId,
            ]);

            return $stmt->fetch() !== false;
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to check active alert.', previous: $exception);
        }
    }

    public function create(Alert $alert): void
    {
        $sql = <<<SQL
            INSERT INTO homelab_alerts (alert_id, severity, title, description, container_id, host_id, created_at)
            VALUES (:alert_id, :severity, :title, :description, :container_id, :host_id, :created_at)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'alert_id'     => $alert->getAlertId(),
                'severity'     => $alert->getSeverity(),
                'title'        => $alert->getTitle(),
                'description'  => $alert->getDescription(),
                'container_id' => $alert->getContainerId(),
                'host_id'      => $alert->getHostId(),
                'created_at'   => $alert->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to store alert.', previous: $exception);
        }
    }

    public function resolve(string $title, ?string $containerId, ?string $hostId): void
    {
        $sql = <<<SQL
            UPDATE homelab_alerts
            SET resolved_at = NOW()
            WHERE resolved_at IS NULL
              AND title = :title
              AND container_id <=> :container_id
              AND host_id <=> :host_id
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'title'        => $title,
                'container_id' => $containerId,
                'host_id'      => $hostId,
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to resolve alert.', previous: $exception);
        }
    }
}
