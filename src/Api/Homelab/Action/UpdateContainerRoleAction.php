<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Homelab\Service\TopologyService;
use LukaLtaApi\Value\Homelab\ContainerId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UpdateContainerRoleAction extends ApiAction
{
    public function __construct(
        private readonly TopologyService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $containerId = ContainerId::fromString($request->getAttribute('containerId'));
        $body        = $request->getParsedBody();

        return $this->service->updateContainerNodeRole($containerId, $body['nodeRole'] ?? null)->getResponse($response);
    }
}
