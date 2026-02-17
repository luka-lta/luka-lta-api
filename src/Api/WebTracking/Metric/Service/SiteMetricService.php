<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\WebTracking\Metric\Service;

use LukaLtaApi\QueryBuilder\Service\MetricQueryService;
use LukaLtaApi\Repository\SiteMetricRepository;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\WebTracking\Site\SiteMetricRequestData;
use LukaLtaApi\Value\WebTracking\Site\SiteMetricResult;

class SiteMetricService
{
    public function __construct(
        private readonly SiteMetricRepository $siteMetricRepository,
        private readonly MetricQueryService   $metricQueryService
    ) {
    }

    public function getSiteMetric(int $siteId, array $queryParams): ApiResult
    {
        $metricRequestData = SiteMetricRequestData::fromQueryParams($queryParams);

        $dataQuery = $this->metricQueryService->getQuery($siteId, $metricRequestData);
        $countQuery = $this->metricQueryService->getQuery($siteId, $metricRequestData, true);

        $dataResult = $this->siteMetricRepository->getSiteMetricData($dataQuery);
        $countResult = $this->siteMetricRepository->getSiteMetricData($countQuery);

        $metricResult = SiteMetricResult::fromResult($dataResult, $countResult);

        return ApiResult::from(JsonResult::from('Noice', [
            'result' => $metricResult->getMetricResult(),
            'totalCount' => $metricResult->getTotalCount(),
        ]));
    }
}
