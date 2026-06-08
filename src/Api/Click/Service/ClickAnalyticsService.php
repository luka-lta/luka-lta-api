<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Click\Service;

use DateTimeImmutable;
use LukaLtaApi\Repository\ClickRepository;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ServerRequestInterface;

class ClickAnalyticsService
{
    public function __construct(
        private readonly ClickRepository $repository,
    ) {
    }

    public function getClicksStats(ServerRequestInterface $request): ApiResult
    {
        $startDate = new DateTimeImmutable($request->getQueryParams()['startDate'] ?? 'now');
        $endDate = new DateTimeImmutable($request->getQueryParams()['endDate'] ?? 'now');

        $clicks = $this->repository->listStats($startDate, $endDate);

        if (empty($clicks)) {
            return ApiResult::from(
                JsonResult::from('No clicks found', ['clicks' => []]),
            );
        }

        return ApiResult::from(JsonResult::from('Clicks found', ['clicks' => $clicks]));
    }

    public function getClickSummary(): ApiResult
    {
        $summary = $this->repository->getSummary();

        return ApiResult::from(JsonResult::from('Summary found', ['summary' => $summary->toArray()]));
    }

    public function getFilters(): ApiResult
    {
        $filters = $this->repository->getFilters();

        return ApiResult::from(JsonResult::from('Filters found', ['filters' => $filters->toArray()]));
    }
}
