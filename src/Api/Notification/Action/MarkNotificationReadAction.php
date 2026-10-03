<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Notification\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Notification\Service\NotificationService;
use LukaLtaApi\Value\User\UserId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MarkNotificationReadAction extends ApiAction
{
    public function __construct(
        private readonly NotificationService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = UserId::fromString($request->getAttribute('userId'));
        $alertId = $request->getAttribute('alertId');

        return $this->service->markRead($userId, $alertId)->getResponse($response);
    }
}
