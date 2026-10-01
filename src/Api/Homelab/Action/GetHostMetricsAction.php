<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Homelab\Service\HomelabService;
use LukaLtaApi\Value\Homelab\HostId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetHostMetricsAction extends ApiAction
{
    private const DEFAULT_RANGE_MINUTES = 12 * 60;

    public function __construct(
        private readonly HomelabService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $hostId       = HostId::fromString($request->getAttribute('hostId'));
        $queryParams  = $request->getQueryParams();
        $metricType   = $queryParams['metric'] ?? 'cpu';
        $rangeMinutes = isset($queryParams['rangeMinutes'])
            ? (int) $queryParams['rangeMinutes']
            : self::DEFAULT_RANGE_MINUTES;

        return $this->service->getHostMetrics($hostId, $metricType, $rangeMinutes)->getResponse($response);
    }
}
