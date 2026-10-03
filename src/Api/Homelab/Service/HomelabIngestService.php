<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Service;

use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\HomelabContainerRepository;
use LukaLtaApi\Repository\HomelabEventRepository;
use LukaLtaApi\Repository\HomelabHostRepository;
use LukaLtaApi\Service\AlertManager;
use LukaLtaApi\Service\AlertTransition;
use LukaLtaApi\Value\Homelab\ContainerId;
use LukaLtaApi\Value\Homelab\Event;
use LukaLtaApi\Value\Homelab\HostId;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;

class HomelabIngestService
{
    /** Host memory usage percent threshold that raises a warning alert. */
    private const HOST_MEMORY_WARNING_PERCENT = 85.0;

    /** Host disk usage percent threshold that raises a warning alert. */
    private const HOST_DISK_WARNING_PERCENT = 90.0;

    /** Restart count that marks a container as crash-looping. */
    private const CONTAINER_RESTART_CRITICAL_COUNT = 10;

    public function __construct(
        private readonly HomelabHostRepository      $hostRepository,
        private readonly HomelabContainerRepository $containerRepository,
        private readonly HomelabEventRepository     $eventRepository,
        private readonly AlertManager               $alertManager,
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
        // poll, running or stopped. Anything we have stored for this host but that's
        // missing from that list was actually removed (docker rm) — delete it instead
        // of leaving a stale row behind forever. Record the removal as an event first;
        // the FK is ON DELETE SET NULL so the event outlives the deleted container row.
        if (isset($data['containerIds']) && is_array($data['containerIds'])) {
            $presentIds = $data['containerIds'];
            foreach ($this->containerRepository->loadByHost($hostId) as $existingContainer) {
                if (in_array($existingContainer->getContainerId(), $presentIds, true)) {
                    continue;
                }

                $this->eventRepository->insert(Event::create(
                    'container.removed',
                    'info',
                    "{$existingContainer->getName()} removed",
                    "Container no longer exists on {$data['name']}.",
                    $existingContainer->getContainerId(),
                    $hostId->asString(),
                ));
            }

            $this->containerRepository->deleteMissing($hostId, $presentIds);
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

        // A stopped container uses no CPU or memory — zero it out instead of keeping
        // the last reported usage, which would otherwise read as still-running load.
        if ($data['status'] === 'stopped') {
            $data['cpuUsagePercent'] = 0.0;
            $data['memoryUsedMb']    = 0.0;
        }

        $isNewContainer = $this->containerRepository->loadContainer($containerId) === null;

        $this->containerRepository->upsert($containerId, $hostId, $data);

        if ($isNewContainer) {
            $this->eventRepository->insert(Event::create(
                'container.added',
                'info',
                "{$data['name']} added",
                "Container first seen on this host.",
                $containerId->asString(),
                $hostId->asString(),
            ));
        }

        $this->containerRepository->insertMetric($containerId, 'cpu', (float) $data['cpuUsagePercent'], $now);
        $this->containerRepository->insertMetric($containerId, 'memory', (float) $data['memoryUsedMb'], $now);

        if (isset($data['restartEvent'])) {
            $this->containerRepository->insertRestartEvent(
                $containerId,
                $data['restartEvent']['reason'],
                new DateTimeImmutable($data['restartEvent']['occurredAt']),
            );
            $this->eventRepository->insert(Event::create(
                'container.restarted',
                'warning',
                "{$data['name']} restarted",
                $data['restartEvent']['reason'],
                $containerId->asString(),
                $hostId->asString(),
            ));
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
        $this->applyAlert(
            $memoryUsagePercent >= self::HOST_MEMORY_WARNING_PERCENT,
            $hostId->asString(),
            'host.memory_high',
            'warning',
            "High memory usage on {$hostName}",
            "Host memory usage at {$memoryUsagePercent}%.",
            ['hostId' => $hostId->asString()],
            null,
            $hostId->asString(),
        );

        $this->applyAlert(
            $diskUsagePercent >= self::HOST_DISK_WARNING_PERCENT,
            $hostId->asString(),
            'host.disk_high',
            'warning',
            "Disk nearly full on {$hostName}",
            "Disk usage at {$diskUsagePercent}%.",
            ['hostId' => $hostId->asString()],
            null,
            $hostId->asString(),
        );
    }

    private function evaluateContainerAlerts(ContainerId $containerId, HostId $hostId, array $data): void
    {
        $context = ['containerId' => $containerId->asString(), 'hostId' => $hostId->asString()];

        $this->applyAlert(
            $data['healthStatus'] === 'unhealthy',
            $containerId->asString(),
            'container.unhealthy',
            'critical',
            "{$data['name']} unhealthy",
            'Container health status is unhealthy.',
            $context,
            $containerId->asString(),
            $hostId->asString(),
        );

        $restartCount = (int) ($data['restartCount'] ?? 0);
        $this->applyAlert(
            $restartCount >= self::CONTAINER_RESTART_CRITICAL_COUNT,
            $containerId->asString(),
            'container.crash_loop',
            'critical',
            "{$data['name']} is crash-looping",
            "Container has restarted {$restartCount} times.",
            $context,
            $containerId->asString(),
            $hostId->asString(),
        );

        $this->applyAlert(
            $data['status'] === 'stopped' && $restartCount > 0,
            $containerId->asString(),
            'container.stopped_after_restart',
            'warning',
            "{$data['name']} stopped unexpectedly",
            "Container is stopped after {$restartCount} restart(s).",
            $context,
            $containerId->asString(),
            $hostId->asString(),
        );
    }

    private function applyAlert(
        bool    $isTriggered,
        string  $sourceId,
        string  $type,
        string  $severity,
        string  $title,
        string  $description,
        array   $context,
        ?string $containerId,
        ?string $hostId,
    ): void {
        $transition = $this->alertManager->evaluate(
            $isTriggered,
            'homelab',
            $sourceId,
            $type,
            $severity,
            $title,
            $description,
            $context,
        );

        if ($transition === AlertTransition::Created) {
            $this->eventRepository->insert(
                Event::create('alert.created', $severity, $title, $description, $containerId, $hostId),
            );
            return;
        }

        if ($transition === AlertTransition::Resolved) {
            $this->eventRepository->insert(
                Event::create('alert.resolved', 'info', $title, "Resolved: {$description}", $containerId, $hostId),
            );
        }
    }
}
