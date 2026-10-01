<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Homelab\Service\HomelabService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ListAlertsAction extends ApiAction
{
    public function __construct(
        private readonly HomelabService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->listAlerts()->getResponse($response);
    }
}
