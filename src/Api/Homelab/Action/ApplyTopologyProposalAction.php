<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Homelab\Service\TopologyProposalService;
use LukaLtaApi\Exception\ApiValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X POST /api/v1/homelab/topology/proposals/<proposalId>/apply --header 'Authorization: <jwt>' \
//   --header 'Origin: <origin>' --data '{"acceptedItemIds":["<itemId>"]}'
class ApplyTopologyProposalAction extends ApiAction
{
    public function __construct(
        private readonly TopologyProposalService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $acceptedItemIds = ((array) ($request->getParsedBody() ?? []))['acceptedItemIds'] ?? null;

        if (!is_array($acceptedItemIds) || !array_is_list($acceptedItemIds)) {
            throw new ApiValidationException("Parameter 'acceptedItemIds' must be a list", 400);
        }

        foreach ($acceptedItemIds as $itemId) {
            if (!is_string($itemId)) {
                throw new ApiValidationException("Parameter 'acceptedItemIds' must contain strings", 400);
            }
        }

        return $this->service
            ->applyProposal($request->getAttribute('proposalId'), $acceptedItemIds)
            ->getResponse($response);
    }
}
