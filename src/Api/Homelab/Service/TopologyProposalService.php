<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiException;
use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Exception\TopologyProposalException;
use LukaLtaApi\Repository\TopologyProposalRepository;
use LukaLtaApi\Value\Homelab\NodeReference;
use LukaLtaApi\Value\Homelab\TopologyProposal;
use LukaLtaApi\Value\Homelab\TopologyProposalItem;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;

class TopologyProposalService
{
    /** Anzahl Proposals in der Übersicht des Dashboards */
    private const int RECENT_PROPOSAL_LIMIT = 20;

    public function __construct(
        private readonly TopologyProposalRepository $proposalRepository,
        private readonly TopologyProposalParser     $parser,
        private readonly TopologyEditor             $editor,
    ) {
    }

    /** Legt ein Proposal an; ein bisher offener Draft wird dabei überholt. */
    public function submitProposal(
        string $title,
        ?string $summary,
        string $createdBy,
        array $rawItems,
    ): TopologyProposal {
        $proposal = $this->parser->parse($title, $summary, $createdBy, $rawItems);
        $this->proposalRepository->createReplacingDrafts($proposal);

        return $proposal;
    }

    public function loadOpenDraft(): ?TopologyProposal
    {
        return $this->proposalRepository->loadOpenDraft();
    }

    public function listProposals(): ApiResult
    {
        return ApiResult::from(JsonResult::from('Topology proposals fetched.', [
            'proposals' => $this->proposalRepository->loadRecentProposals(self::RECENT_PROPOSAL_LIMIT),
        ]));
    }

    public function getProposal(string $proposalId): ApiResult
    {
        return ApiResult::from(JsonResult::from('Topology proposal fetched.', [
            'proposal' => $this->loadProposal($proposalId),
        ]));
    }

    /**
     * Übernimmt die akzeptierten Items in Reihenfolge, alle anderen gelten als abgelehnt.
     * Ein Item, das gegen den inzwischen geänderten Stand nicht mehr passt, wird mit Grund
     * als fehlgeschlagen markiert, statt das ganze Proposal zu kippen.
     *
     * @param string[] $acceptedItemIds
     */
    public function applyProposal(string $proposalId, array $acceptedItemIds): ApiResult
    {
        $proposal = $this->loadDraft($proposalId);
        $accepted = array_fill_keys($acceptedItemIds, true);

        $knownItemIds = array_map(fn (TopologyProposalItem $item) => $item->getItemId(), $proposal->getItems());
        if (array_diff(array_keys($accepted), $knownItemIds) !== []) {
            throw new ApiValidationException('Unknown item id in accepted list', 400);
        }

        $reviewedItems = $this->proposalRepository->executeInTransaction(function () use ($proposal, $accepted) {
            $newNodeIds = [];
            $reviewed   = [];

            foreach ($proposal->getItems() as $item) {
                if (!isset($accepted[$item->getItemId()])) {
                    $reviewed[] = $item->rejected();
                    continue;
                }

                try {
                    $this->applyItem($item, $newNodeIds);
                    $reviewed[] = $item->applied();
                } catch (ApiException $exception) {
                    $reviewed[] = $item->failed($exception->getMessage());
                }
            }

            $status = $accepted === [] ? TopologyProposal::STATUS_REJECTED : TopologyProposal::STATUS_APPLIED;
            $this->proposalRepository->storeReview($proposal->getProposalId(), $status, $reviewed);

            return $reviewed;
        });

        $failedCount = count(array_filter(
            $reviewedItems,
            fn (TopologyProposalItem $item) => $item->getStatus() === TopologyProposalItem::STATUS_FAILED,
        ));

        return ApiResult::from(JsonResult::from('Topology proposal reviewed.', [
            'proposal'    => $this->loadProposal($proposalId),
            'failedCount' => $failedCount,
        ]));
    }

    public function rejectProposal(string $proposalId): ApiResult
    {
        $proposal = $this->loadDraft($proposalId);

        $this->proposalRepository->executeInTransaction(fn () => $this->proposalRepository->storeReview(
            $proposal->getProposalId(),
            TopologyProposal::STATUS_REJECTED,
            array_map(fn (TopologyProposalItem $item) => $item->rejected(), $proposal->getItems()),
        ));

        return ApiResult::from(JsonResult::from('Topology proposal rejected.', [
            'proposal' => $this->loadProposal($proposalId),
        ]));
    }

    /** @param array<string, string> $newNodeIds proposal-lokaler Key => angelegte Knoten-ID */
    private function applyItem(TopologyProposalItem $item, array &$newNodeIds): void
    {
        $payload = $item->getPayload();

        match ($item->getOperation()) {
            TopologyProposalItem::OPERATION_ADD_NODE => $newNodeIds[$payload['key']] = $this->editor->addNode(
                $payload['type'],
                $payload['name'],
                $payload['status'],
                NodeReference::fromArray($payload['ref'] ?? null),
                $payload['metadata'] ?? [],
            )->getNodeId(),
            TopologyProposalItem::OPERATION_UPDATE_NODE => $this->editor->updateNode(
                $payload['nodeId'],
                $payload['type'] ?? null,
                $payload['name'] ?? null,
                $payload['status'] ?? null,
                $payload['metadata'] ?? null,
            ),
            TopologyProposalItem::OPERATION_REMOVE_NODE => $this->editor->removeNode($payload['nodeId']),
            TopologyProposalItem::OPERATION_ADD_EDGE => $this->editor->addEdge(
                $this->resolveEndpoint($payload['source'], $newNodeIds),
                $this->resolveEndpoint($payload['target'], $newNodeIds),
                $payload['relation'],
                $payload['metadata'] ?? [],
            ),
            TopologyProposalItem::OPERATION_REMOVE_EDGE => $this->editor->removeEdge($payload['edgeId']),
        };
    }

    private function resolveEndpoint(string $endpoint, array $newNodeIds): string
    {
        if (!str_starts_with($endpoint, TopologyProposalParser::NEW_NODE_PREFIX)) {
            return $endpoint;
        }

        $key = substr($endpoint, strlen(TopologyProposalParser::NEW_NODE_PREFIX));

        return $newNodeIds[$key] ?? throw new ApiValidationException("New node \"{$key}\" was not applied", 400);
    }

    private function loadProposal(string $proposalId): TopologyProposal
    {
        return $this->proposalRepository->loadProposal($proposalId) ?? throw TopologyProposalException::notFound();
    }

    private function loadDraft(string $proposalId): TopologyProposal
    {
        $proposal = $this->loadProposal($proposalId);
        if (!$proposal->isDraft()) {
            throw TopologyProposalException::alreadyReviewed($proposal->getStatus());
        }

        return $proposal;
    }
}
