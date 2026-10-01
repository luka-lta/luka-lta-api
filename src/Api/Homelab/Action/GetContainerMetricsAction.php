<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Homelab\Service\HomelabService;
use LukaLtaApi\Value\Homelab\ContainerId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetContainerMetricsAction extends ApiAction
{
    private const DEFAULT_RANGE_MINUTES = 12 * 60;

    public function __construct(
        private readonly HomelabService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $containerId  = ContainerId::fromString($request->getAttribute('containerId'));
        $queryParams  = $request->getQueryParams();
        $metricType   = $queryParams['metric'] ?? 'cpu';
        $rangeMinutes = isset($queryParams['rangeMinutes'])
            ? (int) $queryParams['rangeMinutes']
            : self::DEFAULT_RANGE_MINUTES;

        return $this->service->getContainerMetrics($containerId, $metricType, $rangeMinutes)->getResponse($response);
    }
}
