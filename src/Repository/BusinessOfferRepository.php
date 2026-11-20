<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use JsonException;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Repository\Traits\TransactionTrait;
use LukaLtaApi\Value\Business\CustomerId;
use LukaLtaApi\Value\Business\DocumentNumber;
use LukaLtaApi\Value\Business\LineItem;
use LukaLtaApi\Value\Business\LineItems;
use LukaLtaApi\Value\Business\Offer;
use LukaLtaApi\Value\Business\OfferId;
use LukaLtaApi\Value\Business\Offers;
use LukaLtaApi\Value\Business\OfferStatus;
use PDO;
use PDOException;
use Ramsey\Uuid\Uuid;

/**
 * Angebote samt Positionen. create/update/delete öffnen keine eigene Transaktion;
 * der Service klammert zusammengehörige Schreibvorgänge mit executeInTransaction.
 */
class BusinessOfferRepository
{
    use TransactionTrait;

    /** Anzeigename aus dem Kundenstamm; nach dem Versand gewinnt der Snapshot (siehe Offer::fromDatabase) */
    private const string OFFER_SELECT = <<<SQL
        SELECT
            o.offer_id,
            o.offer_number,
            o.customer_id,
            o.status,
            o.offer_date,
            o.valid_until,
            o.intro_text,
            o.closing_text,
            o.total_cents,
            o.customer_snapshot,
            o.issuer_snapshot,
            o.pdf_object_key,
            o.created_at,
            COALESCE(c.company_name, c.contact_name) AS customer_display_name,
            (
                SELECT di.invoice_id
                FROM business_invoices di
                WHERE di.offer_id = o.offer_id AND di.kind = 'deposit' AND di.status <> 'cancelled'
                ORDER BY di.created_at DESC
                LIMIT 1
            ) AS deposit_invoice_id,
            (
                SELECT fi.invoice_id
                FROM business_invoices fi
                WHERE fi.offer_id = o.offer_id AND fi.kind = 'final' AND fi.status <> 'cancelled'
                ORDER BY fi.created_at DESC
                LIMIT 1
            ) AS final_invoice_id
        FROM business_offers o
        JOIN business_customers c ON c.customer_id = o.customer_id
    SQL;

    private const string ITEM_SELECT = <<<SQL
        SELECT
            i.offer_id,
            i.position,
            i.description,
            i.quantity_hundredths,
            i.unit,
            i.unit_price_cents,
            i.line_total_cents
        FROM business_offer_items i
    SQL;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function loadOffer(OfferId $offerId): ?Offer
    {
        $sql = self::OFFER_SELECT . ' WHERE o.offer_id = :offer_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['offer_id' => $offerId->asString()]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch offer', previous: $exception);
        }

        if ($row === false) {
            return null;
        }

        $items = $this->loadItems([$offerId->asString()]);

        return Offer::fromDatabase($row, LineItems::from(...($items[$offerId->asString()] ?? [])));
    }

    public function loadOffers(?OfferStatus $status, ?CustomerId $customerId, ?int $year): Offers
    {
        $conditions = [];
        $params = [];
        if ($status !== null) {
            $conditions[] = 'o.status = :status';
            $params['status'] = $status->value;
        }
        if ($customerId !== null) {
            $conditions[] = 'o.customer_id = :customer_id';
            $params['customer_id'] = $customerId->asString();
        }
        if ($year !== null) {
            // Bereichsabfrage statt YEAR(), damit ein Index auf offer_date nutzbar bliebe
            $conditions[] = 'o.offer_date >= :year_start AND o.offer_date < :year_end';
            $params['year_start'] = sprintf('%04d-01-01', $year);
            $params['year_end'] = sprintf('%04d-01-01', $year + 1);
        }

        $sql = self::OFFER_SELECT;
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY o.offer_date DESC, o.created_at DESC';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch offers', previous: $exception);
        }

        $items = $this->loadItems(array_map(static fn(array $row): string => (string) $row['offer_id'], $rows));

        return Offers::from(...array_map(
            static fn(array $row): Offer => Offer::fromDatabase(
                $row,
                LineItems::from(...($items[(string) $row['offer_id']] ?? [])),
            ),
            $rows,
        ));
    }

    /**
     * Sperrt die Angebotszeile bis zum Ende der laufenden Transaktion und liefert den aktuellen Status.
     * Verhindert, dass parallele Requests (z.B. zweimal Senden) auf einem veralteten Status entscheiden.
     */
    public function lockOfferStatus(OfferId $offerId): ?OfferStatus
    {
        $sql = 'SELECT o.status FROM business_offers o WHERE o.offer_id = :offer_id FOR UPDATE';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['offer_id' => $offerId->asString()]);
            $status = $stmt->fetchColumn();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to lock offer', previous: $exception);
        }

        return $status !== false ? OfferStatus::from((string) $status) : null;
    }

    public function create(Offer $offer): void
    {
        $sql = <<<SQL
            INSERT INTO business_offers
                (offer_id, customer_id, status, offer_date, valid_until, intro_text, closing_text, total_cents)
            VALUES
                (:offer_id, :customer_id, 'draft', :offer_date, :valid_until, :intro_text, :closing_text,
                 :total_cents)
        SQL;

        try {
            $this->pdo->prepare($sql)->execute($offer->toDatabaseRow());
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to create offer', previous: $exception);
        }

        $this->insertItems($offer);
    }

    /** Überschreibt die Kopfdaten eines Entwurfs und ersetzt alle Positionen */
    public function update(Offer $offer): void
    {
        $sql = <<<SQL
            UPDATE business_offers
            SET customer_id = :customer_id,
                offer_date = :offer_date,
                valid_until = :valid_until,
                intro_text = :intro_text,
                closing_text = :closing_text,
                total_cents = :total_cents
            WHERE offer_id = :offer_id AND status = 'draft'
        SQL;

        try {
            $this->pdo->prepare($sql)->execute($offer->toDatabaseRow());
            $this->pdo->prepare('DELETE FROM business_offer_items WHERE offer_id = :offer_id')
                ->execute(['offer_id' => $offer->getOfferId()->asString()]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to update offer', previous: $exception);
        }

        $this->insertItems($offer);
    }

    /** Nur Entwürfe; Positionen fallen per FK-CASCADE mit weg */
    public function delete(OfferId $offerId): void
    {
        $sql = "DELETE FROM business_offers WHERE offer_id = :offer_id AND status = 'draft'";

        try {
            $this->pdo->prepare($sql)->execute(['offer_id' => $offerId->asString()]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to delete offer', previous: $exception);
        }
    }

    /**
     * Schreibt das Angebot fest: Nummer, eingefrorene Kunden-/Absenderdaten und PDF-Key.
     *
     * @param array<string, mixed> $customerSnapshot
     * @param array<string, mixed> $issuerSnapshot
     */
    public function markSent(
        OfferId $offerId,
        DocumentNumber $offerNumber,
        array $customerSnapshot,
        array $issuerSnapshot,
        string $pdfObjectKey,
    ): void {
        $sql = <<<SQL
            UPDATE business_offers
            SET status = 'sent',
                offer_number = :offer_number,
                customer_snapshot = :customer_snapshot,
                issuer_snapshot = :issuer_snapshot,
                pdf_object_key = :pdf_object_key
            WHERE offer_id = :offer_id AND status = 'draft'
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'offer_id' => $offerId->asString(),
                'offer_number' => $offerNumber->asString(),
                'customer_snapshot' => json_encode($customerSnapshot, JSON_THROW_ON_ERROR),
                'issuer_snapshot' => json_encode($issuerSnapshot, JSON_THROW_ON_ERROR),
                'pdf_object_key' => $pdfObjectKey,
            ]);
        } catch (PDOException | JsonException $exception) {
            throw new ApiDatabaseException('Failed to mark offer as sent', previous: $exception);
        }

        // Status wechselt immer von draft auf sent, daher ist genau eine Zeile betroffen
        if ($stmt->rowCount() !== 1) {
            throw new ApiDatabaseException('Offer was not a draft while marking it as sent');
        }
    }

    public function setStatus(OfferId $offerId, OfferStatus $status): void
    {
        $sql = 'UPDATE business_offers SET status = :status WHERE offer_id = :offer_id';

        try {
            $this->pdo->prepare($sql)->execute([
                'offer_id' => $offerId->asString(),
                'status' => $status->value,
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to update offer status', previous: $exception);
        }
    }

    private function insertItems(Offer $offer): void
    {
        $sql = <<<SQL
            INSERT INTO business_offer_items
                (item_id, offer_id, position, description, quantity_hundredths, unit, unit_price_cents,
                 line_total_cents)
            VALUES
                (:item_id, :offer_id, :position, :description, :quantity_hundredths, :unit, :unit_price_cents,
                 :line_total_cents)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($offer->getItems() as $item) {
                $data = $item->toArray();
                $stmt->execute([
                    'item_id' => Uuid::uuid4()->toString(),
                    'offer_id' => $offer->getOfferId()->asString(),
                    'position' => $item->getPosition(),
                    'description' => $data['description'],
                    'quantity_hundredths' => $item->getQuantity()->asHundredths(),
                    'unit' => $data['unit'],
                    'unit_price_cents' => $item->getUnitPrice()->asCents(),
                    'line_total_cents' => $item->getLineTotal()->asCents(),
                ]);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to save offer items', previous: $exception);
        }
    }

    /**
     * @param list<string> $offerIds
     * @return array<string, list<LineItem>> Positionen je Angebots-ID, nach Position sortiert
     */
    private function loadItems(array $offerIds): array
    {
        if ($offerIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($offerIds), '?'));
        $sql = self::ITEM_SELECT . " WHERE i.offer_id IN ($placeholders) ORDER BY i.offer_id, i.position";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($offerIds);
            $items = [];
            foreach ($stmt as $row) {
                $items[(string) $row['offer_id']][] = LineItem::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch offer items', previous: $exception);
        }

        return $items;
    }
}
