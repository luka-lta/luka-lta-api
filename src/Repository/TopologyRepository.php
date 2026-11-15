<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Homelab\NodeReference;
use LukaLtaApi\Value\Homelab\TopologyEdge;
use LukaLtaApi\Value\Homelab\TopologyEdges;
use LukaLtaApi\Value\Homelab\TopologyNode;
use LukaLtaApi\Value\Homelab\TopologyNodes;
use PDO;
use PDOException;

class TopologyRepository
{
    private const string NODE_SELECT = <<<SQL
        SELECT node_id, type, name, status, ref_type, ref_host_id, ref_service_key, metadata, updated_at
        FROM homelab_topology_nodes
    SQL;

    private const string EDGE_SELECT = <<<SQL
        SELECT edge_id, source_node_id, target_node_id, relation, metadata
        FROM homelab_topology_edges
    SQL;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function loadAllNodes(): TopologyNodes
    {
        try {
            $stmt = $this->pdo->query(self::NODE_SELECT . ' ORDER BY name ASC');

            $nodes = [];
            foreach ($stmt as $row) {
                $nodes[] = TopologyNode::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch topology nodes', previous: $exception);
        }

        return TopologyNodes::from(...$nodes);
    }

    public function loadNode(string $nodeId): ?TopologyNode
    {
        try {
            $stmt = $this->pdo->prepare(self::NODE_SELECT . ' WHERE node_id = :node_id');
            $stmt->execute(['node_id' => $nodeId]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch topology node', previous: $exception);
        }

        return $row !== false ? TopologyNode::fromDatabase($row) : null;
    }

    public function loadNodeByReference(NodeReference $reference): ?TopologyNode
    {
        $sql = self::NODE_SELECT . <<<SQL
             WHERE ref_type = :ref_type
               AND ref_host_id <=> :ref_host_id
               AND ref_service_key <=> :ref_service_key
             LIMIT 1
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'ref_type'        => $reference->getType(),
                'ref_host_id'     => $reference->getHostId()?->asString(),
                'ref_service_key' => $reference->getServiceKey()?->asString(),
            ]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch topology node', previous: $exception);
        }

        return $row !== false ? TopologyNode::fromDatabase($row) : null;
    }

    public function createNode(TopologyNode $node): void
    {
        $sql = <<<SQL
            INSERT INTO homelab_topology_nodes
                (node_id, type, name, status, ref_type, ref_host_id, ref_service_key, metadata)
            VALUES
                (:node_id, :type, :name, :status, :ref_type, :ref_host_id, :ref_service_key, :metadata)
        SQL;

        $reference = $node->getReference();

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'node_id'         => $node->getNodeId(),
                'type'            => $node->getType(),
                'name'            => $node->getName(),
                'status'          => $node->getStatus(),
                'ref_type'        => $reference->getType(),
                'ref_host_id'     => $reference->getHostId()?->asString(),
                'ref_service_key' => $reference->getServiceKey()?->asString(),
                'metadata'        => json_encode($node->getMetadata(), JSON_THROW_ON_ERROR),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to create topology node', previous: $exception);
        }
    }

    public function updateNode(TopologyNode $node): void
    {
        $sql = <<<SQL
            UPDATE homelab_topology_nodes
            SET type = :type, name = :name, status = :status, metadata = :metadata
            WHERE node_id = :node_id
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
            throw new ApiDatabaseException('Failed to update topology node', previous: $exception);
        }
    }

    /** Kanten des Knotens fallen per FK (ON DELETE CASCADE) mit weg. */
    public function deleteNode(string $nodeId): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM homelab_topology_nodes WHERE node_id = :node_id');
            $stmt->execute(['node_id' => $nodeId]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to delete topology node', previous: $exception);
        }
    }

    public function loadAllEdges(): TopologyEdges
    {
        try {
            $stmt = $this->pdo->query(self::EDGE_SELECT);

            $edges = [];
            foreach ($stmt as $row) {
                $edges[] = TopologyEdge::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch topology edges', previous: $exception);
        }

        return TopologyEdges::from(...$edges);
    }

    public function loadEdge(string $edgeId): ?TopologyEdge
    {
        try {
            $stmt = $this->pdo->prepare(self::EDGE_SELECT . ' WHERE edge_id = :edge_id');
            $stmt->execute(['edge_id' => $edgeId]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch topology edge', previous: $exception);
        }

        return $row !== false ? TopologyEdge::fromDatabase($row) : null;
    }

    public function createEdge(TopologyEdge $edge): void
    {
        $sql = <<<SQL
            INSERT INTO homelab_topology_edges (edge_id, source_node_id, target_node_id, relation, metadata)
            VALUES (:edge_id, :source_node_id, :target_node_id, :relation, :metadata)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'edge_id'        => $edge->getEdgeId(),
                'source_node_id' => $edge->getSourceNodeId(),
                'target_node_id' => $edge->getTargetNodeId(),
                'relation'       => $edge->getRelation(),
                'metadata'       => json_encode($edge->getMetadata(), JSON_THROW_ON_ERROR),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to create topology edge', previous: $exception);
        }
    }

    public function deleteEdge(string $edgeId): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM homelab_topology_edges WHERE edge_id = :edge_id');
            $stmt->execute(['edge_id' => $edgeId]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to delete topology edge', previous: $exception);
        }
    }
}
