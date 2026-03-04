<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\WebTracking\TrackingUser\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\WebTracking\TrackingUser\Service\TrackingUserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetTrackingUserAction extends ApiAction
{
    public function __construct(
        private readonly TrackingUserService $trackingUserService
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $siteId = (int)$request->getAttribute('siteId');
        $trackingUserId = $request->getAttribute('trackingUserId');

        return $this->trackingUserService->getTrackingUser($siteId, $trackingUserId)->getResponse($response);
    }
}
