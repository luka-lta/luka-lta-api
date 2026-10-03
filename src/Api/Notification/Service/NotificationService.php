<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Notification\Service;

use LukaLtaApi\Exception\AlertNotFoundException;
use LukaLtaApi\Repository\AlertRepository;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\User\UserId;

class NotificationService
{
    private const int DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly AlertRepository $alertRepository,
    ) {
    }

    public function listFeed(UserId $userId, int $limit, int $offset): ApiResult
    {
        $limit = $limit > 0 ? $limit : self::DEFAULT_LIMIT;

        $feed = array_map(
            static fn (array $row) => [
                ...$row['alert']->jsonSerialize(),
                'isRead' => $row['isRead'],
            ],
            $this->alertRepository->loadFeed($userId, $limit, $offset),
        );

        return ApiResult::from(JsonResult::from('Notifications fetched.', [
            'notifications' => $feed,
            'unreadCount' => $this->alertRepository->countUnread($userId),
        ]));
    }

    public function markRead(UserId $userId, string $alertId): ApiResult
    {
        if ($this->alertRepository->findById($alertId) === null) {
            throw new AlertNotFoundException();
        }

        $this->alertRepository->markRead($userId, $alertId);

        return ApiResult::from(JsonResult::from('Notification marked as read.'));
    }

    public function markAllRead(UserId $userId): ApiResult
    {
        $this->alertRepository->markAllRead($userId);

        return ApiResult::from(JsonResult::from('All notifications marked as read.'));
    }
}
