<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Notification\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Notification\Service\NotificationService;
use LukaLtaApi\Value\User\UserId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MarkAllNotificationsReadAction extends ApiAction
{
    public function __construct(
        private readonly NotificationService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = UserId::fromString($request->getAttribute('userId'));

        return $this->service->markAllRead($userId)->getResponse($response);
    }
}
