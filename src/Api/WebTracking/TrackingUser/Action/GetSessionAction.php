<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\WebTracking\TrackingUser\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\WebTracking\TrackingUser\Service\TrackingUserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetSessionAction extends ApiAction
{
    public function __construct(
        private readonly TrackingUserService $trackingUserService,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $siteId = (int)$request->getAttribute('siteId');
        $sessionId = $request->getAttribute('sessionId');
        $limit = (int)$request->getQueryParams()['limit'];
        $offset = (int)$request->getQueryParams()['offset'];

        return $this->trackingUserService->getSession($siteId, $sessionId, $limit, $offset)->getResponse($response);
    }
}
