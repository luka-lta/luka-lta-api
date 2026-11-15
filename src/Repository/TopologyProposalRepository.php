<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Exception\TopologyProposalException;
use LukaLtaApi\Repository\Traits\TransactionTrait;
use LukaLtaApi\Value\Homelab\TopologyProposal;
use LukaLtaApi\Value\Homelab\TopologyProposalItem;
use PDO;
use PDOException;

class TopologyProposalRepository
{
    use TransactionTrait;

    private const string PROPOSAL_SELECT = <<<SQL
        SELECT proposal_id, title, summary, status, created_by, created_at, reviewed_at
        FROM homelab_topology_proposals
    SQL;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /** @return TopologyProposal[] neueste zuerst */
    public function loadRecentProposals(int $limit): array
    {
        try {
            $stmt = $this->pdo->prepare(self::PROPOSAL_SELECT . ' ORDER BY created_at DESC LIMIT :limit');
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch topology proposals', previous: $exception);
        }

        return array_map(
            fn (array $row) => TopologyProposal::fromDatabase($row, $this->loadItems($row['proposal_id'])),
            $rows,
        );
    }

    public function loadProposal(string $proposalId): ?TopologyProposal
    {
        try {
            $stmt = $this->pdo->prepare(self::PROPOSAL_SELECT . ' WHERE proposal_id = :proposal_id');
            $stmt->execute(['proposal_id' => $proposalId]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch topology proposal', previous: $exception);
        }

        if ($row === false) {
            return null;
        }

        return TopologyProposal::fromDatabase($row, $this->loadItems($proposalId));
    }

    public function loadOpenDraft(): ?TopologyProposal
    {
        try {
            $stmt = $this->pdo->prepare(
                self::PROPOSAL_SELECT . ' WHERE status = :status ORDER BY created_at DESC LIMIT 1',
            );
            $stmt->execute(['status' => TopologyProposal::STATUS_DRAFT]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch open topology proposal', previous: $exception);
        }

        if ($row === false) {
            return null;
        }

        return TopologyProposal::fromDatabase($row, $this->loadItems($row['proposal_id']));
    }

    /** Legt das Proposal an und markiert bisher offene Drafts als überholt. */
    public function createReplacingDrafts(TopologyProposal $proposal): void
    {
        $this->executeInTransaction(function () use ($proposal): void {
            $stmt = $this->pdo->prepare(
                'UPDATE homelab_topology_proposals SET status = :superseded WHERE status = :draft',
            );
            $stmt->execute([
                'superseded' => TopologyProposal::STATUS_SUPERSEDED,
                'draft'      => TopologyProposal::STATUS_DRAFT,
            ]);

            $stmt = $this->pdo->prepare(<<<SQL
                INSERT INTO homelab_topology_proposals (proposal_id, title, summary, status, created_by)
                VALUES (:proposal_id, :title, :summary, :status, :created_by)
            SQL);
            $stmt->execute([
                'proposal_id' => $proposal->getProposalId(),
                'title'       => $proposal->getTitle(),
                'summary'     => $proposal->getSummary(),
                'status'      => $proposal->getStatus(),
                'created_by'  => $proposal->getCreatedBy(),
            ]);

            $stmt = $this->pdo->prepare(<<<SQL
                INSERT INTO homelab_topology_proposal_items
                    (item_id, proposal_id, position, operation, payload, reasoning, evidence, status)
                VALUES
                    (:item_id, :proposal_id, :position, :operation, :payload, :reasoning, :evidence, :status)
            SQL);
            foreach ($proposal->getItems() as $item) {
                $stmt->execute([
                    'item_id'     => $item->getItemId(),
                    'proposal_id' => $proposal->getProposalId(),
                    'position'    => $item->getPosition(),
                    'operation'   => $item->getOperation(),
                    'payload'     => json_encode($item->getPayload(), JSON_THROW_ON_ERROR),
                    'reasoning'   => $item->getReasoning(),
                    'evidence'    => json_encode($item->getEvidence(), JSON_THROW_ON_ERROR),
                    'status'      => $item->getStatus(),
                ]);
            }
        });
    }

    /**
     * Schreibt das Review-Ergebnis. Nur innerhalb einer laufenden Transaktion aufrufen
     * (siehe executeInTransaction), damit Topologie-Änderungen und Status zusammen landen.
     *
     * @param TopologyProposalItem[] $items
     */
    public function storeReview(string $proposalId, string $status, array $items): void
    {
        try {
            $stmt = $this->pdo->prepare(<<<SQL
                UPDATE homelab_topology_proposals
                SET status = :status, reviewed_at = CURRENT_TIMESTAMP
                WHERE proposal_id = :proposal_id
                  AND status = :draft
            SQL);
            $stmt->execute([
                'status'      => $status,
                'proposal_id' => $proposalId,
                'draft'       => TopologyProposal::STATUS_DRAFT,
            ]);

            // Paralleles Review desselben Drafts: Der zweite Durchlauf rollt komplett zurück.
            if ($stmt->rowCount() === 0) {
                throw TopologyProposalException::alreadyReviewed('reviewed');
            }

            $stmt = $this->pdo->prepare(<<<SQL
                UPDATE homelab_topology_proposal_items
                SET status = :status, failure = :failure
                WHERE item_id = :item_id
            SQL);
            foreach ($items as $item) {
                $stmt->execute([
                    'status'  => $item->getStatus(),
                    'failure' => $item->getFailure(),
                    'item_id' => $item->getItemId(),
                ]);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to store topology proposal review', previous: $exception);
        }
    }

    /** @return TopologyProposalItem[] */
    private function loadItems(string $proposalId): array
    {
        $sql = <<<SQL
            SELECT item_id, position, operation, payload, reasoning, evidence, status, failure
            FROM homelab_topology_proposal_items
            WHERE proposal_id = :proposal_id
            ORDER BY position ASC
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['proposal_id' => $proposalId]);
            $rows = $stmt->fetchAll();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch topology proposal items', previous: $exception);
        }

        return array_map(TopologyProposalItem::fromDatabase(...), $rows);
    }
}
