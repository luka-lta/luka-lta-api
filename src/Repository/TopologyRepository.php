<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Homelab\TopologyEdge;
use LukaLtaApi\Value\Homelab\TopologyEdges;
use LukaLtaApi\Value\Homelab\TopologyNode;
use LukaLtaApi\Value\Homelab\TopologyNodes;
use PDO;
use PDOException;

class TopologyRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function loadAllNodes(): TopologyNodes
    {
        $sql = 'SELECT node_id, type, name, status, metadata, updated_at FROM homelab_topology_nodes ORDER BY name ASC';

        try {
            $stmt = $this->pdo->query($sql);

            $nodes = [];
            foreach ($stmt as $row) {
                $nodes[] = TopologyNode::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch topology nodes.', previous: $exception);
        }

        return TopologyNodes::from(...$nodes);
    }

    public function loadNode(string $nodeId): ?TopologyNode
    {
        $sql = <<<SQL
            SELECT node_id, type, name, status, metadata, updated_at
            FROM homelab_topology_nodes
            WHERE node_id = :node_id
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['node_id' => $nodeId]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch topology node.', previous: $exception);
        }

        return $row !== false ? TopologyNode::fromDatabase($row) : null;
    }

    public function createNode(TopologyNode $node): void
    {
        $sql = <<<SQL
            INSERT INTO homelab_topology_nodes (node_id, type, name, status, metadata)
            VALUES (:node_id, :type, :name, :status, :metadata)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'node_id'  => $node->getNodeId(),
                'type'     => $node->getType(),
                'name'     => $node->getName(),
                'status'   => $node->getStatus(),
                'metadata' => json_encode($node->getMetadata(), JSON_THROW_ON_ERROR),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to create topology node.', previous: $exception);
        }
    }

    public function deleteNode(string $nodeId): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM homelab_topology_nodes WHERE node_id = :node_id');
            $stmt->execute(['node_id' => $nodeId]);

            $stmt = $this->pdo->prepare(
                'DELETE FROM homelab_topology_edges
                 WHERE (source_type = "node" AND source_id = :node_id)
                    OR (target_type = "node" AND target_id = :node_id)'
            );
            $stmt->execute(['node_id' => $nodeId]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to delete topology node.', previous: $exception);
        }
    }

    public function loadAllEdges(): TopologyEdges
    {
        $sql = <<<SQL
            SELECT edge_id, source_type, source_id, target_type, target_id, relation, metadata
            FROM homelab_topology_edges
        SQL;

        try {
            $stmt = $this->pdo->query($sql);

            $edges = [];
            foreach ($stmt as $row) {
                $edges[] = TopologyEdge::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch topology edges.', previous: $exception);
        }

        return TopologyEdges::from(...$edges);
    }

    public function createEdge(TopologyEdge $edge): void
    {
        $sql = <<<SQL
            INSERT INTO homelab_topology_edges
                (edge_id, source_type, source_id, target_type, target_id, relation, metadata)
            VALUES
                (:edge_id, :source_type, :source_id, :target_type, :target_id, :relation, :metadata)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'edge_id'     => $edge->getEdgeId(),
                'source_type' => $edge->getSourceType(),
                'source_id'   => $edge->getSourceId(),
                'target_type' => $edge->getTargetType(),
                'target_id'   => $edge->getTargetId(),
                'relation'    => $edge->getRelation(),
                'metadata'    => json_encode($edge->getMetadata(), JSON_THROW_ON_ERROR),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to create topology edge.', previous: $exception);
        }
    }

    public function deleteEdge(string $edgeId): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM homelab_topology_edges WHERE edge_id = :edge_id');
            $stmt->execute(['edge_id' => $edgeId]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to delete topology edge.', previous: $exception);
        }
    }
}
