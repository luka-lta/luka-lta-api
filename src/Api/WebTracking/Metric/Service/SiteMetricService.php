<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\WebTracking\Metric\Service;

use LukaLtaApi\Repository\SiteMetricRepository;
use LukaLtaApi\Service\MetricQueryService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\Tracking\MetricRequestData;

class SiteMetricService
{
    public function __construct(
        private readonly SiteMetricRepository $siteMetricRepository,
        private readonly MetricQueryService   $metricQueryService
    ) {
    }

    public function getSiteMetric(int $siteId, array $queryParams): ApiResult
    {
        $page = $queryParams['page'] ?? null;

        $isPaginatedRequest = $page !== null;

        $metricRequestData = MetricRequestData::fromQueryParams($queryParams);

        $dataQuery = $this->metricQueryService->getQuery($siteId, $metricRequestData);
        $countQuery = $this->metricQueryService->getQuery($siteId, $metricRequestData, true);

        $dataResult = $this->siteMetricRepository->getSiteMetricData($dataQuery);
        $countResult = $this->siteMetricRepository->getSiteMetricData($countQuery);

        var_dump($dataResult);
        var_dump($countResult);

        return ApiResult::from(JsonResult::from('Noice'));
    }
}
