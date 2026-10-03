<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Notification\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Notification\Service\NotificationService;
use LukaLtaApi\Value\User\UserId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ListNotificationsAction extends ApiAction
{
    public function __construct(
        private readonly NotificationService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = UserId::fromString($request->getAttribute('userId'));
        $query = $request->getQueryParams();
        $limit = (int) ($query['limit'] ?? 50);
        $offset = (int) ($query['offset'] ?? 0);

        return $this->service->listFeed($userId, $limit, $offset)->getResponse($response);
    }
}
