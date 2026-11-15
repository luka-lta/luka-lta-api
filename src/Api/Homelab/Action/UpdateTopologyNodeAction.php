<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Homelab\Service\TopologyService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X PATCH /api/v1/homelab/topology/nodes/<nodeId> --header 'Authorization: <jwt>' \
//   --header 'Origin: <origin>' --data '{"type":"database","name":"MySQL"}'
class UpdateTopologyNodeAction extends ApiAction
{
    public function __construct(
        private readonly TopologyService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service
            ->updateNode($request->getAttribute('nodeId'), (array) ($request->getParsedBody() ?? []))
            ->getResponse($response);
    }
}
