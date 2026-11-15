<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Homelab\Service\TopologyProposalService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X POST /api/v1/homelab/topology/proposals/<proposalId>/reject --header 'Authorization: <jwt>' \
//   --header 'Origin: <origin>'
class RejectTopologyProposalAction extends ApiAction
{
    public function __construct(
        private readonly TopologyProposalService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->rejectProposal($request->getAttribute('proposalId'))->getResponse($response);
    }
}
