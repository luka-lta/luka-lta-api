<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Homelab\Service\TopologyProposalService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl /api/v1/homelab/topology/proposals/<proposalId> --header 'Authorization: <jwt>' \
//   --header 'Origin: <origin>'
class GetTopologyProposalAction extends ApiAction
{
    public function __construct(
        private readonly TopologyProposalService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getProposal($request->getAttribute('proposalId'))->getResponse($response);
    }
}
