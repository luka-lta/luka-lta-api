<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Service;

use LukaLtaApi\Exception\ApiException;
use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Repository\TopologyRepository;
use LukaLtaApi\Value\Homelab\NodeReference;
use LukaLtaApi\Value\Homelab\TopologyEdge;
use LukaLtaApi\Value\Homelab\TopologyNode;
use LukaLtaApi\Value\Homelab\TopologyProposal;
use LukaLtaApi\Value\Homelab\TopologyProposalItem;

/**
 * Parst einen eingereichten Vorschlag (z. B. von Claude per MCP) in ein Proposal und prüft jedes
 * Item gegen den aktuellen Stand, damit der Reviewer nur umsetzbare Änderungen zu sehen bekommt.
 * Neue Knoten bekommen einen proposal-lokalen `key`; Kanten verweisen darauf mit `new:<key>`.
 */
class TopologyProposalParser
{
    public const string NEW_NODE_PREFIX = 'new:';

    /** Erlaubte Form eines proposal-lokalen Knoten-Schlüssels */
    private const string KEY_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/i';

    public function __construct(
        private readonly TopologyRepository $topologyRepository,
        private readonly TopologyEditor     $editor,
    ) {
    }

    public function parse(string $title, ?string $summary, string $createdBy, array $rawItems): TopologyProposal
    {
        if (!array_is_list($rawItems)) {
            throw new ApiValidationException('Items must be a list', 400);
        }

        if (count($rawItems) > TopologyProposal::MAX_ITEMS) {
            throw new ApiValidationException('Proposal has too many items', 400);
        }

        $nodeIds = [];
        foreach ($this->topologyRepository->loadAllNodes() as $node) {
            $nodeIds[$node->getNodeId()] = $node->getReference();
        }

        $edgeIds = [];
        foreach ($this->topologyRepository->loadAllEdges() as $edge) {
            $edgeIds[$edge->getEdgeId()] = true;
        }

        $state = ['nodeIds' => $nodeIds, 'edgeIds' => $edgeIds, 'newKeys' => [], 'newReferences' => []];
        $items = [];

        foreach ($rawItems as $index => $rawItem) {
            $position = $index + 1;

            try {
                $items[] = $this->parseItem($position, $rawItem, $state);
            } catch (ApiException $exception) {
                throw new ApiValidationException("Item {$position}: {$exception->getMessage()}", 400, $exception);
            }
        }

        return TopologyProposal::create($title, $summary, $createdBy, $items);
    }

    private function parseItem(int $position, mixed $rawItem, array &$state): TopologyProposalItem
    {
        if (!is_array($rawItem)) {
            throw new ApiValidationException('Item must be an object', 400);
        }

        $operation = (string) ($rawItem['operation'] ?? '');
        $payload   = $rawItem['payload'] ?? null;
        if (!is_array($payload)) {
            throw new ApiValidationException('Payload must be an object', 400);
        }

        $evidence = $rawItem['evidence'] ?? [];
        if (!is_array($evidence) || !array_is_list($evidence)) {
            throw new ApiValidationException('Evidence must be a list of strings', 400);
        }

        $normalized = match ($operation) {
            TopologyProposalItem::OPERATION_ADD_NODE    => $this->parseAddNode($payload, $state),
            TopologyProposalItem::OPERATION_UPDATE_NODE => $this->parseUpdateNode($payload, $state),
            TopologyProposalItem::OPERATION_REMOVE_NODE => $this->parseRemoveNode($payload, $state),
            TopologyProposalItem::OPERATION_ADD_EDGE    => $this->parseAddEdge($payload, $state),
            TopologyProposalItem::OPERATION_REMOVE_EDGE => $this->parseRemoveEdge($payload, $state),
            default => throw new ApiValidationException('Invalid proposal operation', 400),
        };

        return TopologyProposalItem::create(
            $position,
            $operation,
            $normalized,
            (string) ($rawItem['reasoning'] ?? ''),
            $evidence,
        );
    }

    private function parseAddNode(array $payload, array &$state): array
    {
        $key = (string) ($payload['key'] ?? '');
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new ApiValidationException('add_node needs a key (letters, digits, "-", "_")', 400);
        }

        if (isset($state['newKeys'][$key])) {
            throw new ApiValidationException("Key \"{$key}\" is used twice", 400);
        }

        $rawReference = $payload['ref'] ?? null;
        if ($rawReference !== null && !is_array($rawReference)) {
            throw new ApiValidationException('ref must be an object', 400);
        }

        $reference = NodeReference::fromArray($rawReference);
        $metadata  = $this->optionalArray($payload, 'metadata') ?? [];
        $node      = TopologyNode::create(
            (string) ($payload['type'] ?? ''),
            (string) ($payload['name'] ?? ''),
            (string) ($payload['status'] ?? 'unknown'),
            $reference,
            $metadata,
        );

        if (!$reference->isNone()) {
            $this->editor->assertReferenceTargetExists($reference);
            $this->assertReferenceUnmapped($reference, $state);
            $state['newReferences'][] = $reference;
        }

        $state['newKeys'][$key] = true;

        return [
            'key'      => $key,
            'type'     => $node->getType(),
            'name'     => $node->getName(),
            'status'   => $node->getStatus(),
            'ref'      => $reference->jsonSerialize(),
            'metadata' => (object) $node->getMetadata(),
        ];
    }

    private function parseUpdateNode(array $payload, array $state): array
    {
        $nodeId = $this->existingNodeId($payload, $state);

        $changes = [];
        foreach (['type', 'name', 'status'] as $field) {
            if (isset($payload[$field])) {
                $changes[$field] = (string) $payload[$field];
            }
        }

        $metadata = $this->optionalArray($payload, 'metadata');
        if ($metadata !== null) {
            $changes['metadata'] = $metadata;
        }

        if ($changes === []) {
            throw new ApiValidationException('update_node needs at least one change', 400);
        }

        // Prüft die Werte mit denselben Regeln wie beim Speichern.
        $this->topologyRepository->loadNode($nodeId)?->withChanges(
            $changes['type'] ?? null,
            $changes['name'] ?? null,
            $changes['status'] ?? null,
            $changes['metadata'] ?? null,
        );

        if (isset($changes['metadata'])) {
            $changes['metadata'] = (object) $changes['metadata'];
        }

        return ['nodeId' => $nodeId, ...$changes];
    }

    private function parseRemoveNode(array $payload, array &$state): array
    {
        $nodeId = $this->existingNodeId($payload, $state);
        unset($state['nodeIds'][$nodeId]);

        return ['nodeId' => $nodeId];
    }

    private function parseAddEdge(array $payload, array $state): array
    {
        $source   = $this->edgeEndpoint($payload, 'source', $state);
        $target   = $this->edgeEndpoint($payload, 'target', $state);
        $metadata = $this->optionalArray($payload, 'metadata') ?? [];
        $edge     = TopologyEdge::create($source, $target, (string) ($payload['relation'] ?? ''), $metadata);

        return [
            'source'   => $source,
            'target'   => $target,
            'relation' => $edge->getRelation(),
            'metadata' => (object) $edge->getMetadata(),
        ];
    }

    private function parseRemoveEdge(array $payload, array &$state): array
    {
        $edgeId = (string) ($payload['edgeId'] ?? '');
        if (!isset($state['edgeIds'][$edgeId])) {
            throw new ApiValidationException("Edge \"{$edgeId}\" does not exist", 400);
        }

        unset($state['edgeIds'][$edgeId]);

        return ['edgeId' => $edgeId];
    }

    private function edgeEndpoint(array $payload, string $field, array $state): string
    {
        $value = (string) ($payload[$field] ?? '');

        if (str_starts_with($value, self::NEW_NODE_PREFIX)) {
            $key = substr($value, strlen(self::NEW_NODE_PREFIX));
            if (!isset($state['newKeys'][$key])) {
                throw new ApiValidationException("{$field} refers to unknown new node \"{$key}\"", 400);
            }

            return $value;
        }

        if (!isset($state['nodeIds'][$value])) {
            throw new ApiValidationException("{$field} node \"{$value}\" does not exist", 400);
        }

        return $value;
    }

    private function existingNodeId(array $payload, array $state): string
    {
        $nodeId = (string) ($payload['nodeId'] ?? '');
        if (!isset($state['nodeIds'][$nodeId])) {
            throw new ApiValidationException("Node \"{$nodeId}\" does not exist", 400);
        }

        return $nodeId;
    }

    private function assertReferenceUnmapped(NodeReference $reference, array $state): void
    {
        foreach ([...array_values($state['nodeIds']), ...$state['newReferences']] as $mapped) {
            if ($mapped->equals($reference)) {
                throw new ApiValidationException('Reference is already mapped by another node', 400);
            }
        }
    }

    private function optionalArray(array $payload, string $key): ?array
    {
        $value = $payload[$key] ?? null;
        if ($value !== null && !is_array($value)) {
            throw new ApiValidationException("{$key} must be an object", 400);
        }

        return $value;
    }
}
