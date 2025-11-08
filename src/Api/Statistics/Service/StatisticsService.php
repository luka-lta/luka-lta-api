<?php

namespace LukaLtaApi\Api\Statistics\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiInvalidArgumentException;
use LukaLtaApi\Repository\StatisticRepository;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\Stats\BrowserUsageStat;
use LukaLtaApi\Value\Stats\DeviceStat;
use LukaLtaApi\Value\Stats\MarketUsageStat;
use LukaLtaApi\Value\Stats\OperationSystemStat;
use Psr\Http\Message\ServerRequestInterface;

class StatisticsService
{
    public function __construct(
        private readonly StatisticRepository $repository,
    ) {
    }

    public function getStatistics(ServerRequestInterface $request): ApiResult
    {
        $statsType = $request->getQueryParams()['statistic'];

        switch ($statsType) {
            case 'os':
                $class = OperationSystemStat::class;
                break;
            case 'browser':
                $class = BrowserUsageStat::class;
                break;
            case 'device':
                $class = DeviceStat::class;
                break;
            case 'market':
                $class = MarketUsageStat::class;
                break;
            default:
                throw new ApiInvalidArgumentException(
                    'Unknown stats type',
                    StatusCodeInterface::STATUS_BAD_REQUEST
                );
        }

        $stats = $this->repository->getStats($statsType, $statsType, $class);

        if ($stats->count() === 0) {
            return ApiResult::from(
                JsonResult::from('No statistics found', ['statistics' => []]),
            );
        }

        return ApiResult::from(
            JsonResult::from(
                'Statistics found',
                [
                    'statistics' => $stats->toFrontend()
                ]
            )
        );
    }
}
