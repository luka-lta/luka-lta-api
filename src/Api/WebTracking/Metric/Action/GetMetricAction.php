<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\WebTracking\Metric\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\WebTracking\Metric\Service\SiteMetricService;
use LukaLtaApi\Value\Filter\RequestFilter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetMetricAction extends ApiAction
{
    public function __construct(
        private readonly SiteMetricService $metricService,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $siteId = (int)$request->getAttribute('siteId');
        $queryParams = $request->getQueryParams();

        return $this->metricService->getSiteMetric($siteId, $queryParams)->getResponse($response);
    }
}
