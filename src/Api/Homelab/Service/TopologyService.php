<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Repository\AlertRepository;
use LukaLtaApi\Repository\HomelabContainerRepository;
use LukaLtaApi\Repository\HomelabHostRepository;
use LukaLtaApi\Repository\TopologyRepository;
use LukaLtaApi\Value\Homelab\NodeReference;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;

class TopologyService
{
    public function __construct(
        private readonly TopologyRepository         $topologyRepository,
        private readonly HomelabHostRepository      $hostRepository,
        private readonly HomelabContainerRepository $containerRepository,
        private readonly AlertRepository            $alertRepository,
        private readonly TopologyGraphBuilder       $graphBuilder,
        private readonly TopologyEditor             $editor,
    ) {
    }

    public function loadGraph(): array
    {
        return $this->graphBuilder->build(
            $this->hostRepository->loadAll(),
            $this->containerRepository->loadAll(),
            $this->topologyRepository->loadAllNodes(),
            $this->topologyRepository->loadAllEdges(),
            $this->alertRepository->loadActive('homelab'),
        );
    }

    public function getGraph(): ApiResult
    {
        return ApiResult::from(JsonResult::from('Topology graph fetched.', $this->loadGraph()));
    }

    public function createNode(array $data): ApiResult
    {
        $node = $this->editor->addNode(
            (string) $data['type'],
            (string) $data['name'],
            (string) ($data['status'] ?? 'unknown'),
            NodeReference::fromArray($this->optionalArray($data, 'ref')),
            $this->optionalArray($data, 'metadata') ?? [],
        );

        return ApiResult::from(
            JsonResult::from('Topology node created.', ['node' => $node]),
            StatusCodeInterface::STATUS_CREATED,
        );
    }

    public function updateNode(string $nodeId, array $data): ApiResult
    {
        $node = $this->editor->updateNode(
            $nodeId,
            isset($data['type']) ? (string) $data['type'] : null,
            isset($data['name']) ? (string) $data['name'] : null,
            isset($data['status']) ? (string) $data['status'] : null,
            $this->optionalArray($data, 'metadata'),
        );

        return ApiResult::from(JsonResult::from('Topology node updated.', ['node' => $node]));
    }

    public function deleteNode(string $nodeId): ApiResult
    {
        $this->editor->removeNode($nodeId);

        return ApiResult::from(JsonResult::from('Topology node deleted.'), StatusCodeInterface::STATUS_NO_CONTENT);
    }

    public function createEdge(array $data): ApiResult
    {
        $edge = $this->editor->addEdge(
            (string) $data['sourceId'],
            (string) $data['targetId'],
            (string) $data['relation'],
            $this->optionalArray($data, 'metadata') ?? [],
        );

        return ApiResult::from(
            JsonResult::from('Topology edge created.', ['edge' => $edge]),
            StatusCodeInterface::STATUS_CREATED,
        );
    }

    public function deleteEdge(string $edgeId): ApiResult
    {
        $this->editor->removeEdge($edgeId);

        return ApiResult::from(JsonResult::from('Topology edge deleted.'), StatusCodeInterface::STATUS_NO_CONTENT);
    }

    private function optionalArray(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !is_array($value)) {
            throw new ApiValidationException("Field '{$key}' must be an object", 400);
        }

        return $value;
    }
}
