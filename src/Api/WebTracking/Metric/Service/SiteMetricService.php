<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\WebTracking\Metric\Service;

use LukaLtaApi\QueryBuilder\Service\MetricQueryService;
use LukaLtaApi\Repository\SiteMetricRepository;
use LukaLtaApi\Value\Filter\ColumnFilterCollection;
use LukaLtaApi\Value\Filter\RequestFilter;
use LukaLtaApi\Value\Request\RequestQueryParams;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
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
        $filters = isset($queryParams['filters'])
            ? RequestFilter::fromQueryParams($siteId, $queryParams['filters'])->getFilterCollection()
            : ColumnFilterCollection::from();

        $metricRequestData = RequestQueryParams::fromQueryParams($queryParams);

        ['sql' => $dataSQL, 'params' => $dataParams] = $this->metricQueryService->getQuery(
            $siteId,
            $metricRequestData,
            false,
            $filters
        );

        ['sql' => $countSQL, 'params' => $countParams] = $this->metricQueryService->getQuery(
            $siteId,
            $metricRequestData,
            true,
            $filters
        );

        $dataResult  = $this->siteMetricRepository->getSiteMetricData($dataSQL, $dataParams);
        $countResult = $this->siteMetricRepository->getSiteMetricData($countSQL, $countParams);

        $metricResult = SiteMetricResult::fromResult($dataResult, $countResult);

        return ApiResult::from(JsonResult::from('Metrics found', [
            'data'       => $metricResult->getMetricResult(),
            'totalCount' => $metricResult->getTotalCount(),
        ]));
    }
}
