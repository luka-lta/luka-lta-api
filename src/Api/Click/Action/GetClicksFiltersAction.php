<?php

namespace LukaLtaApi\Api\Click\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Click\Service\ClickService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetClicksFiltersAction extends ApiAction
{
    public function __construct(
        private readonly ClickService $clickService,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->clickService->getFilters()->getResponse($response);
    }
}
