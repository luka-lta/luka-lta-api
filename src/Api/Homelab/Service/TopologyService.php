<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Exception\ContainerNotFoundException;
use LukaLtaApi\Exception\HostNotFoundException;
use LukaLtaApi\Repository\HomelabAlertRepository;
use LukaLtaApi\Repository\HomelabContainerRepository;
use LukaLtaApi\Repository\HomelabHostRepository;
use LukaLtaApi\Repository\TopologyRepository;
use LukaLtaApi\Value\Homelab\ContainerId;
use LukaLtaApi\Value\Homelab\HostId;
use LukaLtaApi\Value\Homelab\TopologyEdge;
use LukaLtaApi\Value\Homelab\TopologyNode;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;

class TopologyService
{
    private const VALID_NODE_STATUSES = ['healthy', 'warning', 'offline', 'unknown'];
    private const VALID_EDGE_TYPES    = ['host', 'container', 'node'];
    private const VALID_RELATIONS     = ['routes_to', 'depends_on', 'connects_to', 'hosted_on', 'exposes'];

    public function __construct(
        private readonly TopologyRepository         $topologyRepository,
        private readonly HomelabHostRepository       $hostRepository,
        private readonly HomelabContainerRepository  $containerRepository,
        private readonly HomelabAlertRepository      $alertRepository,
        private readonly TopologyGraphBuilder        $graphBuilder,
    ) {
    }

    public function getGraph(): ApiResult
    {
        $graph = $this->graphBuilder->build(
            $this->hostRepository->loadAll(),
            $this->containerRepository->loadAll(),
            $this->topologyRepository->loadAllNodes(),
            $this->topologyRepository->loadAllEdges(),
            $this->alertRepository->loadActive(),
        );

        return ApiResult::from(
            JsonResult::from('Topology graph fetched.', $graph)
        );
    }

    public function createNode(array $data): ApiResult
    {
        $status = $data['status'] ?? 'unknown';
        if (!in_array($status, self::VALID_NODE_STATUSES, true)) {
            throw new ApiValidationException('Invalid node status.', 400);
        }

        $node = TopologyNode::create($data['type'], $data['name'], $status, $data['metadata'] ?? []);
        $this->topologyRepository->createNode($node);

        return ApiResult::from(
            JsonResult::from('Topology node created.', ['node' => $node]),
            StatusCodeInterface::STATUS_CREATED,
        );
    }

    public function deleteNode(string $nodeId): ApiResult
    {
        $this->topologyRepository->deleteNode($nodeId);

        return ApiResult::from(JsonResult::from('Topology node deleted.'), StatusCodeInterface::STATUS_NO_CONTENT);
    }

    public function createEdge(array $data): ApiResult
    {
        $sourceTypeValid = in_array($data['sourceType'], self::VALID_EDGE_TYPES, true);
        $targetTypeValid = in_array($data['targetType'], self::VALID_EDGE_TYPES, true);

        if (!$sourceTypeValid || !$targetTypeValid) {
            throw new ApiValidationException('Invalid edge endpoint type.', 400);
        }

        if (!in_array($data['relation'], self::VALID_RELATIONS, true)) {
            throw new ApiValidationException('Invalid edge relation.', 400);
        }

        $edge = TopologyEdge::create(
            $data['sourceType'],
            $data['sourceId'],
            $data['targetType'],
            $data['targetId'],
            $data['relation'],
            $data['metadata'] ?? [],
        );
        $this->topologyRepository->createEdge($edge);

        return ApiResult::from(
            JsonResult::from('Topology edge created.', ['edge' => $edge]),
            StatusCodeInterface::STATUS_CREATED,
        );
    }

    public function deleteEdge(string $edgeId): ApiResult
    {
        $this->topologyRepository->deleteEdge($edgeId);

        return ApiResult::from(JsonResult::from('Topology edge deleted.'), StatusCodeInterface::STATUS_NO_CONTENT);
    }

    public function updateHostNodeType(HostId $hostId, string $nodeType): ApiResult
    {
        if ($this->hostRepository->loadHost($hostId) === null) {
            throw new HostNotFoundException();
        }

        $this->hostRepository->updateNodeType($hostId, $nodeType);

        return ApiResult::from(JsonResult::from('Host node type updated.'));
    }

    public function updateContainerNodeRole(ContainerId $containerId, ?string $nodeRole): ApiResult
    {
        if ($this->containerRepository->loadContainer($containerId) === null) {
            throw new ContainerNotFoundException();
        }

        $this->containerRepository->updateNodeRole($containerId, $nodeRole);

        return ApiResult::from(JsonResult::from('Container node role updated.'));
    }
}
