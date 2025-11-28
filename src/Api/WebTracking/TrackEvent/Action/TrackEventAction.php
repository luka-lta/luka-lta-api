<?php

namespace LukaLtaApi\Api\WebTracking\TrackEvent\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\WebTracking\TrackEvent\Service\TrackEventService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TrackEventAction extends ApiAction
{
    public function __construct(
        private readonly TrackEventService $service
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->trackEvent($request)->getResponse($response);
    }
}
