<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Service;

use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\HomelabAlertRepository;
use LukaLtaApi\Repository\HomelabContainerRepository;
use LukaLtaApi\Repository\HomelabHostRepository;
use LukaLtaApi\Value\Homelab\Alert;
use LukaLtaApi\Value\Homelab\ContainerId;
use LukaLtaApi\Value\Homelab\HostId;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;

class HomelabIngestService
{
    /** Host memory usage percent threshold that raises a warning alert. */
    private const HOST_MEMORY_WARNING_PERCENT = 85.0;

    /** Host disk usage percent threshold that raises a warning alert. */
    private const HOST_DISK_WARNING_PERCENT = 90.0;

    public function __construct(
        private readonly HomelabHostRepository      $hostRepository,
        private readonly HomelabContainerRepository $containerRepository,
        private readonly HomelabAlertRepository      $alertRepository,
    ) {
    }

    public function ingestHost(array $data): ApiResult
    {
        $hostId = HostId::fromString($data['hostId']);
        $now    = new DateTimeImmutable();

        $this->hostRepository->upsert($hostId, $data);

        $this->hostRepository->insertMetric($hostId, 'cpu', (float) $data['cpuUsagePercent'], $now);
        $this->hostRepository->insertMetric($hostId, 'disk', $this->percentage(
            (float) $data['diskUsedGb'],
            (float) $data['diskTotalGb'],
        ), $now);

        $memoryUsagePercent = $this->percentage((float) $data['memoryUsedGb'], (float) $data['memoryTotalGb']);
        $this->hostRepository->insertMetric($hostId, 'memory', $memoryUsagePercent, $now);

        $this->hostRepository->insertMetric($hostId, 'network_in', (float) ($data['networkInMbps'] ?? 0.0), $now);
        $this->hostRepository->insertMetric($hostId, 'network_out', (float) ($data['networkOutMbps'] ?? 0.0), $now);

        $this->evaluateHostAlerts($hostId, $data['name'], $memoryUsagePercent, $this->percentage(
            (float) $data['diskUsedGb'],
            (float) $data['diskTotalGb'],
        ));

        // The agent reports every container it currently sees (docker ps -a) on each
        // poll. Anything we have stored for this host but that's missing from that
        // list no longer exists on the host — mark it stopped instead of leaving a
        // stale "running" row behind forever.
        if (isset($data['containerIds']) && is_array($data['containerIds'])) {
            $this->containerRepository->markMissingAsStopped($hostId, $data['containerIds']);
        }

        return ApiResult::from(
            JsonResult::from('Host metrics ingested.'),
            StatusCodeInterface::STATUS_ACCEPTED,
        );
    }

    public function ingestContainer(array $data): ApiResult
    {
        $containerId = ContainerId::fromString($data['containerId']);
        $hostId      = HostId::fromString($data['hostId']);
        $now         = new DateTimeImmutable();

        $this->containerRepository->upsert($containerId, $hostId, $data);

        $this->containerRepository->insertMetric($containerId, 'cpu', (float) $data['cpuUsagePercent'], $now);
        $this->containerRepository->insertMetric($containerId, 'memory', (float) $data['memoryUsedMb'], $now);

        if (isset($data['restartEvent'])) {
            $this->containerRepository->insertRestartEvent(
                $containerId,
                $data['restartEvent']['reason'],
                new DateTimeImmutable($data['restartEvent']['occurredAt']),
            );
        }

        if (isset($data['healthCheckEvent'])) {
            $this->containerRepository->insertHealthCheckEvent(
                $containerId,
                $data['healthCheckEvent']['status'],
                $data['healthCheckEvent']['message'],
                new DateTimeImmutable($data['healthCheckEvent']['checkedAt']),
            );
        }

        foreach ($data['logs'] ?? [] as $log) {
            $this->containerRepository->insertLogLine(
                $containerId,
                $log['level'],
                $log['message'],
                new DateTimeImmutable($log['loggedAt']),
            );
        }

        $this->evaluateContainerAlerts($containerId, $hostId, $data);

        return ApiResult::from(
            JsonResult::from('Container metrics ingested.'),
            StatusCodeInterface::STATUS_ACCEPTED,
        );
    }

    private function percentage(float $used, float $total): float
    {
        if ($total <= 0.0) {
            return 0.0;
        }

        return round($used / $total * 100, 1);
    }

    private function evaluateHostAlerts(
        HostId $hostId,
        string $hostName,
        float $memoryUsagePercent,
        float $diskUsagePercent,
    ): void {
        $this->evaluateThresholdAlert(
            $memoryUsagePercent >= self::HOST_MEMORY_WARNING_PERCENT,
            'warning',
            "High memory usage on {$hostName}",
            "Host memory usage at {$memoryUsagePercent}%.",
            null,
            $hostId->asString(),
        );

        $this->evaluateThresholdAlert(
            $diskUsagePercent >= self::HOST_DISK_WARNING_PERCENT,
            'warning',
            "Disk nearly full on {$hostName}",
            "Disk usage at {$diskUsagePercent}%.",
            null,
            $hostId->asString(),
        );
    }

    private function evaluateContainerAlerts(ContainerId $containerId, HostId $hostId, array $data): void
    {
        $this->evaluateThresholdAlert(
            $data['healthStatus'] === 'unhealthy',
            'critical',
            "{$data['name']} unhealthy",
            "Container health status is unhealthy.",
            $containerId->asString(),
            $hostId->asString(),
        );
    }

    private function evaluateThresholdAlert(
        bool $isTriggered,
        string $severity,
        string $title,
        string $description,
        ?string $containerId,
        ?string $hostId,
    ): void {
        $hasActiveAlert = $this->alertRepository->hasActiveAlert($title, $containerId, $hostId);

        if ($isTriggered && !$hasActiveAlert) {
            $this->alertRepository->create(Alert::create($severity, $title, $description, $containerId, $hostId));
            return;
        }

        if (!$isTriggered && $hasActiveAlert) {
            $this->alertRepository->resolve($title, $containerId, $hostId);
        }
    }
}
