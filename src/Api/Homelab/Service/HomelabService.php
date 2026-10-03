<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Service;

use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Exception\ContainerNotFoundException;
use LukaLtaApi\Exception\HostNotFoundException;
use LukaLtaApi\Repository\AlertRepository;
use LukaLtaApi\Repository\HomelabContainerRepository;
use LukaLtaApi\Repository\HomelabEventRepository;
use LukaLtaApi\Repository\HomelabHostRepository;
use LukaLtaApi\Value\Alert\Alert;
use LukaLtaApi\Value\Homelab\ContainerId;
use LukaLtaApi\Value\Homelab\HostId;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;

class HomelabService
{
    private const ALLOWED_HOST_METRICS = ['cpu', 'memory', 'disk', 'network_in', 'network_out'];
    private const ALLOWED_CONTAINER_METRICS = ['cpu', 'memory'];

    public function __construct(
        private readonly HomelabHostRepository      $hostRepository,
        private readonly HomelabContainerRepository $containerRepository,
        private readonly AlertRepository             $alertRepository,
        private readonly HomelabEventRepository      $eventRepository,
    ) {
    }

    public function listHosts(): ApiResult
    {
        $hosts = $this->hostRepository->loadAll();

        return ApiResult::from(
            JsonResult::from('Hosts fetched.', ['hosts' => $hosts])
        );
    }

    public function getHostMetrics(HostId $hostId, string $metricType, int $rangeMinutes): ApiResult
    {
        if (!in_array($metricType, self::ALLOWED_HOST_METRICS, true)) {
            throw new ApiValidationException('Unknown metric type for host.', 400);
        }

        if ($this->hostRepository->loadHost($hostId) === null) {
            throw new HostNotFoundException();
        }

        $series = $this->hostRepository->loadMetrics($hostId, $metricType, $rangeMinutes);

        return ApiResult::from(
            JsonResult::from('Host metrics fetched.', ['metrics' => $series])
        );
    }

    public function listContainers(): ApiResult
    {
        $containers = $this->containerRepository->loadAll();

        return ApiResult::from(
            JsonResult::from('Containers fetched.', ['containers' => $containers])
        );
    }

    public function getContainer(ContainerId $containerId): ApiResult
    {
        $container = $this->containerRepository->loadContainer($containerId);

        if ($container === null) {
            throw new ContainerNotFoundException();
        }

        $container->setCpuHistory($this->containerRepository->loadMetrics($containerId, 'cpu', 12 * 60));
        $container->setMemoryHistory($this->containerRepository->loadMetrics($containerId, 'memory', 12 * 60));
        $container->setRestartHistory($this->containerRepository->loadRestartHistory($containerId));
        $container->setHealthCheckHistory($this->containerRepository->loadHealthCheckHistory($containerId));
        $container->setLogs($this->containerRepository->loadLogs($containerId));

        return ApiResult::from(
            JsonResult::from('Container fetched.', ['container' => $container])
        );
    }

    public function getContainerMetrics(ContainerId $containerId, string $metricType, int $rangeMinutes): ApiResult
    {
        if (!in_array($metricType, self::ALLOWED_CONTAINER_METRICS, true)) {
            throw new ApiValidationException('Unknown metric type for container.', 400);
        }

        if ($this->containerRepository->loadContainer($containerId) === null) {
            throw new ContainerNotFoundException();
        }

        $series = $this->containerRepository->loadMetrics($containerId, $metricType, $rangeMinutes);

        return ApiResult::from(
            JsonResult::from('Container metrics fetched.', ['metrics' => $series])
        );
    }

    public function listAlerts(): ApiResult
    {
        $alerts = array_map(
            $this->toLegacyHomelabAlertShape(...),
            iterator_to_array($this->alertRepository->loadActive('homelab')),
        );

        return ApiResult::from(
            JsonResult::from('Alerts fetched.', ['alerts' => $alerts])
        );
    }

    private function toLegacyHomelabAlertShape(Alert $alert): array
    {
        $context = $alert->getContext();

        return [
            'id' => $alert->getAlertId(),
            'severity' => $alert->getSeverity(),
            'title' => $alert->getTitle(),
            'description' => $alert->getDescription(),
            'timestamp' => $alert->getFirstOccurredAt()->format('Y-m-d H:i:s'),
            'containerId' => $context['containerId'] ?? null,
            'hostId' => $context['hostId'] ?? null,
        ];
    }

    public function listEvents(int $limit = 100): ApiResult
    {
        $events = $this->eventRepository->loadRecent($limit);

        return ApiResult::from(
            JsonResult::from('Events fetched.', ['events' => $events])
        );
    }
}
