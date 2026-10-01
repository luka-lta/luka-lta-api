<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Homelab\Service\TopologyService;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreateTopologyEdgeAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
        private readonly TopologyService  $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->requestValidator->validate($request, [
            'sourceType' => ['required' => true, 'location' => 'body'],
            'sourceId'   => ['required' => true, 'location' => 'body'],
            'targetType' => ['required' => true, 'location' => 'body'],
            'targetId'   => ['required' => true, 'location' => 'body'],
            'relation'   => ['required' => true, 'location' => 'body'],
            'metadata'   => ['required' => false, 'location' => 'body'],
        ]);

        return $this->service->createEdge($request->getParsedBody())->getResponse($response);
    }
}
