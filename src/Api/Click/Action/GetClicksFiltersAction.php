<?php

namespace LukaLtaApi\Api\Click\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Click\Service\ClickAnalyticsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetClicksFiltersAction extends ApiAction
{
    public function __construct(
        private readonly ClickAnalyticsService $clickService,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->clickService->getFilters()->getResponse($response);
    }
}
