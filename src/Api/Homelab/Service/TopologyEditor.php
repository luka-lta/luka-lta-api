<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Service;

use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Exception\TopologyConflictException;
use LukaLtaApi\Exception\TopologyEdgeNotFoundException;
use LukaLtaApi\Exception\TopologyNodeNotFoundException;
use LukaLtaApi\Repository\HomelabContainerRepository;
use LukaLtaApi\Repository\HomelabHostRepository;
use LukaLtaApi\Repository\TopologyRepository;
use LukaLtaApi\Value\Homelab\NodeReference;
use LukaLtaApi\Value\Homelab\TopologyEdge;
use LukaLtaApi\Value\Homelab\TopologyNode;

/**
 * Einzige Stelle, die die kuratierte Topologie verändert — genutzt von den Dashboard-Endpoints
 * und beim Übernehmen von Proposals, damit beide Wege dieselben Regeln durchsetzen.
 */
class TopologyEditor
{
    public function __construct(
        private readonly TopologyRepository         $topologyRepository,
        private readonly HomelabHostRepository      $hostRepository,
        private readonly HomelabContainerRepository $containerRepository,
    ) {
    }

    public function addNode(
        string $type,
        string $name,
        string $status,
        NodeReference $reference,
        array $metadata,
    ): TopologyNode {
        $node = TopologyNode::create($type, $name, $status, $reference, $metadata);

        if (!$reference->isNone()) {
            $this->assertReferenceTargetExists($reference);

            $existing = $this->topologyRepository->loadNodeByReference($reference);
            if ($existing !== null) {
                throw TopologyConflictException::referenceAlreadyMapped($existing->getName());
            }
        }

        $this->topologyRepository->createNode($node);

        return $node;
    }

    public function updateNode(
        string $nodeId,
        ?string $type,
        ?string $name,
        ?string $status,
        ?array $metadata,
    ): TopologyNode {
        $node = $this->loadNode($nodeId)->withChanges($type, $name, $status, $metadata);
        $this->topologyRepository->updateNode($node);

        return $node;
    }

    public function removeNode(string $nodeId): void
    {
        $this->loadNode($nodeId);
        $this->topologyRepository->deleteNode($nodeId);
    }

    public function addEdge(string $sourceNodeId, string $targetNodeId, string $relation, array $metadata): TopologyEdge
    {
        $edge = TopologyEdge::create($sourceNodeId, $targetNodeId, $relation, $metadata);

        $this->loadNode($sourceNodeId);
        $this->loadNode($targetNodeId);

        foreach ($this->topologyRepository->loadAllEdges() as $existing) {
            if ($existing->connects($sourceNodeId, $targetNodeId, $relation)) {
                throw TopologyConflictException::edgeAlreadyExists();
            }
        }

        $this->topologyRepository->createEdge($edge);

        return $edge;
    }

    public function removeEdge(string $edgeId): void
    {
        if ($this->topologyRepository->loadEdge($edgeId) === null) {
            throw new TopologyEdgeNotFoundException();
        }

        $this->topologyRepository->deleteEdge($edgeId);
    }

    public function assertReferenceTargetExists(NodeReference $reference): void
    {
        $hostId = $reference->getHostId();
        if ($hostId === null) {
            return;
        }

        if ($this->hostRepository->loadHost($hostId) === null) {
            throw new ApiValidationException("Referenced host \"{$hostId->asString()}\" does not exist", 400);
        }

        $serviceKey = $reference->getServiceKey();
        if ($serviceKey !== null && !$this->containerRepository->hasService($hostId, $serviceKey)) {
            throw new ApiValidationException("Referenced service \"{$serviceKey}\" does not exist on host", 400);
        }
    }

    private function loadNode(string $nodeId): TopologyNode
    {
        return $this->topologyRepository->loadNode($nodeId) ?? throw new TopologyNodeNotFoundException();
    }
}
