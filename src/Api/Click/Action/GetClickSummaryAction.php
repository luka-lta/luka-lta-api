<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Click\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Click\Service\ClickService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetClickSummaryAction extends ApiAction
{
    public function __construct(
        private readonly ClickService $service
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getClickSummary()->getResponse($response);
    }
}
