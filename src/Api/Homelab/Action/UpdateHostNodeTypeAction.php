<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Homelab\Service\TopologyService;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Value\Homelab\HostId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UpdateHostNodeTypeAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
        private readonly TopologyService  $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->requestValidator->validate($request, [
            'nodeType' => ['required' => true, 'location' => 'body'],
        ]);

        $hostId = HostId::fromString($request->getAttribute('hostId'));
        $body   = $request->getParsedBody();

        return $this->service->updateHostNodeType($hostId, $body['nodeType'])->getResponse($response);
    }
}
