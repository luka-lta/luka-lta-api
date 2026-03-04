<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\WebTracking\TrackingUser\Service;

use LukaLtaApi\QueryBuilder\QueryComponentBuilder;
use LukaLtaApi\QueryBuilder\Value\QueryContext;
use LukaLtaApi\Repository\SessionRepository;
use LukaLtaApi\Repository\TrackingUserRepository;
use LukaLtaApi\Value\Filter\ColumnFilterCollection;
use LukaLtaApi\Value\Request\RequestQueryParams;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;

class TrackingUserService
{
    public function __construct(
        private readonly TrackingUserRepository $trackingUserRepository,
        private readonly SessionRepository $sessionRepository,
        private readonly QueryComponentBuilder $queryComponentBuilder,
    ) {
    }

    public function getTrackingUsers(int $siteId, array $queryParams): ApiResult
    {
        $limit = isset($queryParams['limit']) ? (int) $queryParams['limit'] : 100;
        $offset = isset($queryParams['offset']) ? (int) $queryParams['offset'] : 0;

        $trackingUsers = $this->trackingUserRepository->getAllTrackingUsers(
            $siteId,
            $limit,
            $offset,
        );

        if (!$trackingUsers) {
            return ApiResult::from(
                JsonResult::from('No Tracking Users found.'),
            );
        }

        return ApiResult::from(
            JsonResult::from('Tracking Users Found.', [
                'users' => $trackingUsers['results'],
                'totalCount' => $trackingUsers['count'],
            ]),
        );
    }

    public function getTrackingUser(int $siteId, string $trackingUserId): ApiResult
    {
        $trackingUser = $this->trackingUserRepository->getTrackingUser($siteId, $trackingUserId);

        if (!$trackingUser) {
            return ApiResult::from(
                JsonResult::from('Tracking User not found.'),
            );
        }

        return ApiResult::from(
            JsonResult::from('Tracking User Found.', [
                'user' => $trackingUser,
            ])
        );
    }

    public function getSessions(int $siteId, array $queryParams): ApiResult
    {
        $trackingUserId = $queryParams['trackingUserId'];
        $queryContext = QueryContext::from(
            $siteId,
            RequestQueryParams::fromQueryParams($queryParams),
            false,
            $this->queryComponentBuilder,
            ColumnFilterCollection::from(),
        );

        $sessions = $this->sessionRepository->getSessionsFromTrackingUser(
            $trackingUserId,
            $queryContext,
        );

        if (!$sessions) {
            return ApiResult::from(
                JsonResult::from('Sessions not found.'),
            );
        }

        return ApiResult::from(
            JsonResult::from('Sessions Found.', [
                'sessions' => $sessions,
            ])
        );
    }

    public function getSession(int $siteId, string $sessionId, int $limit, int $offset): ApiResult
    {
        $result = $this->sessionRepository->getSession($siteId, $sessionId, $limit, $offset);

        return ApiResult::from(
            JsonResult::from('Session Found.', [
                'data' => $result,
            ])
        );
    }
}
