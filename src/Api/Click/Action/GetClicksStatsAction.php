<?php

namespace LukaLtaApi\Api\Click\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Click\Service\ClickAnalyticsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetClicksStatsAction extends ApiAction
{
    public function __construct(
        private readonly ClickAnalyticsService $service
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getClicksStats($request)->getResponse($response);
    }
}
