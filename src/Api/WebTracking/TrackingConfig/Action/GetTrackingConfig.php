<?php

namespace LukaLtaApi\Api\WebTracking\TrackingConfig\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\WebTracking\TrackingConfig\Service\TrackingConfigService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetTrackingConfig extends ApiAction
{
    public function __construct(
        private readonly TrackingConfigService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getSiteConfig($request->getAttribute('siteId'))->getResponse($response);
    }
}
