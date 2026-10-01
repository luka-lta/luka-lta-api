<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Homelab\Service\TopologyService;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreateTopologyNodeAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
        private readonly TopologyService  $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->requestValidator->validate($request, [
            'type'     => ['required' => true, 'location' => 'body'],
            'name'     => ['required' => true, 'location' => 'body'],
            'status'   => ['required' => false, 'location' => 'body'],
            'metadata' => ['required' => false, 'location' => 'body'],
        ]);

        return $this->service->createNode($request->getParsedBody())->getResponse($response);
    }
}
