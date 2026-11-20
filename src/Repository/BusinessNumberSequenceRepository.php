<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Business\DocumentNumber;
use LukaLtaApi\Value\Business\DocumentPrefix;
use PDO;
use PDOException;

/**
 * Lückenlose Nummernkreise je Präfix und Jahr.
 *
 * Öffnet bewusst keine eigene Transaktion: der Aufrufer schreibt den Beleg in derselben Transaktion
 * fest (gleiche PDO-Instanz aus dem Container). Wird sie zurückgerollt, ist auch die Nummer nicht verbraucht.
 */
class BusinessNumberSequenceRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * Vergibt die nächste Nummer. Die Sequenzzeile bleibt bis zum Ende der Transaktion gesperrt,
     * parallele Vergaben warten also aufeinander.
     */
    public function allocateNext(DocumentPrefix $prefix, int $year): DocumentNumber
    {
        $next = $this->lockSequence($prefix, $year) + 1;
        $update = <<<SQL
            UPDATE business_number_sequences
            SET `last_value` = :last_value
            WHERE prefix = :prefix AND `year` = :year
        SQL;

        try {
            $this->pdo->prepare($update)->execute([
                'last_value' => $next,
                'prefix' => $prefix->value,
                'year' => $year,
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to allocate document number', previous: $exception);
        }

        return DocumentNumber::from($prefix, $year, $next);
    }

    /**
     * Legt die Sequenzzeile bei Bedarf an, sperrt sie bis zum Ende der Transaktion (FOR UPDATE) und liefert den
     * aktuellen Zählerstand. Vergabe und Import desselben Kreises und Jahres laufen dadurch nacheinander.
     */
    public function lockSequence(DocumentPrefix $prefix, int $year): int
    {
        $this->assertInTransaction();

        $ensureRow = <<<SQL
            INSERT INTO business_number_sequences (prefix, `year`, `last_value`)
            VALUES (:prefix, :year, 0)
            ON DUPLICATE KEY UPDATE `last_value` = `last_value`
        SQL;
        $select = <<<SQL
            SELECT s.`last_value`
            FROM business_number_sequences s
            WHERE s.prefix = :prefix AND s.`year` = :year
            FOR UPDATE
        SQL;
        $key = ['prefix' => $prefix->value, 'year' => $year];

        try {
            $this->pdo->prepare($ensureRow)->execute($key);
            $stmt = $this->pdo->prepare($select);
            $stmt->execute($key);
            $lastValue = $stmt->fetchColumn();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to lock number sequence', previous: $exception);
        }

        if ($lastValue === false) {
            throw new ApiDatabaseException('Number sequence row is missing');
        }

        return (int) $lastValue;
    }

    /** Hebt den Zählerstand an (z.B. nach einem Import), senkt ihn aber nie */
    public function raiseTo(DocumentPrefix $prefix, int $year, int $value): void
    {
        $sql = <<<SQL
            INSERT INTO business_number_sequences (prefix, `year`, `last_value`)
            VALUES (:prefix, :year, :value)
            ON DUPLICATE KEY UPDATE `last_value` = GREATEST(`last_value`, :raise_value)
        SQL;

        try {
            $this->pdo->prepare($sql)->execute([
                'prefix' => $prefix->value,
                'year' => $year,
                'value' => $value,
                'raise_value' => $value,
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to raise number sequence', previous: $exception);
        }
    }

    private function assertInTransaction(): void
    {
        if (!$this->pdo->inTransaction()) {
            throw new ApiDatabaseException('Number allocation requires an open transaction');
        }
    }
}
