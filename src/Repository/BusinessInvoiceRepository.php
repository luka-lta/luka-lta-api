<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use JsonException;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Repository\Traits\TransactionTrait;
use LukaLtaApi\Value\Business\BusinessClock;
use LukaLtaApi\Value\Business\CustomerId;
use LukaLtaApi\Value\Business\DocumentNumber;
use LukaLtaApi\Value\Business\Invoice;
use LukaLtaApi\Value\Business\InvoiceId;
use LukaLtaApi\Value\Business\Invoices;
use LukaLtaApi\Value\Business\InvoiceStatus;
use LukaLtaApi\Value\Business\LineItem;
use LukaLtaApi\Value\Business\LineItems;
use PDO;
use PDOException;
use Ramsey\Uuid\Uuid;

/**
 * Rechnungen samt Positionen. create/update/delete/markFinalized öffnen keine eigene Transaktion;
 * der Service klammert zusammengehörige Schreibvorgänge mit executeInTransaction.
 */
class BusinessInvoiceRepository
{
    use TransactionTrait;

    /** Anzeigename aus dem Kundenstamm; nach dem Festschreiben gewinnt der Snapshot (siehe Invoice::fromDatabase) */
    private const string INVOICE_SELECT = <<<SQL
        SELECT
            i.invoice_id,
            i.invoice_number,
            i.kind,
            i.status,
            i.customer_id,
            i.offer_id,
            i.deposit_invoice_id,
            i.cancels_invoice_id,
            i.invoice_date,
            i.service_date,
            i.service_period_start,
            i.service_period_end,
            i.payment_term_days,
            i.due_date,
            i.intro_text,
            i.closing_text,
            i.subtotal_cents,
            i.deposit_deduction_cents,
            i.total_cents,
            i.paid_at,
            i.is_imported,
            i.customer_snapshot,
            i.issuer_snapshot,
            i.pdf_object_key,
            i.created_at,
            COALESCE(c.company_name, c.contact_name) AS customer_display_name,
            o.offer_number,
            di.invoice_number AS deposit_invoice_number,
            di.invoice_date AS deposit_invoice_date,
            ci.invoice_number AS cancels_invoice_number
        FROM business_invoices i
        JOIN business_customers c ON c.customer_id = i.customer_id
        LEFT JOIN business_offers o ON o.offer_id = i.offer_id
        LEFT JOIN business_invoices di ON di.invoice_id = i.deposit_invoice_id
        LEFT JOIN business_invoices ci ON ci.invoice_id = i.cancels_invoice_id
    SQL;

    private const string ITEM_SELECT = <<<SQL
        SELECT
            it.invoice_id,
            it.position,
            it.description,
            it.quantity_hundredths,
            it.unit,
            it.unit_price_cents,
            it.line_total_cents
        FROM business_invoice_items it
    SQL;

    /** Felder, die ein Entwurf per PATCH ändern darf; Art und Verknüpfungen bleiben fest */
    private const array UPDATABLE_COLUMNS = [
        'invoice_id',
        'customer_id',
        'invoice_date',
        'service_date',
        'service_period_start',
        'service_period_end',
        'payment_term_days',
        'intro_text',
        'closing_text',
        'subtotal_cents',
        'deposit_deduction_cents',
        'total_cents',
    ];

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function isInTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function loadInvoice(InvoiceId $invoiceId): ?Invoice
    {
        $sql = self::INVOICE_SELECT . ' WHERE i.invoice_id = :invoice_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['invoice_id' => $invoiceId->asString()]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch invoice', previous: $exception);
        }

        if ($row === false) {
            return null;
        }

        $items = $this->loadItems([$invoiceId->asString()]);

        return Invoice::fromDatabase($row, LineItems::from(...($items[$invoiceId->asString()] ?? [])));
    }

    /** @param bool $onlyOverdue offen und Fälligkeit vor dem heutigen Tag (deutsche Zeit) */
    public function loadInvoices(
        ?InvoiceStatus $status,
        ?CustomerId $customerId,
        ?int $year,
        bool $onlyOverdue,
    ): Invoices {
        $conditions = [];
        $params = [];
        if ($status !== null) {
            $conditions[] = 'i.status = :status';
            $params['status'] = $status->value;
        }
        if ($customerId !== null) {
            $conditions[] = 'i.customer_id = :customer_id';
            $params['customer_id'] = $customerId->asString();
        }
        if ($year !== null) {
            // Bereichsabfrage statt YEAR(), damit ein Index auf invoice_date nutzbar bliebe
            $conditions[] = 'i.invoice_date >= :year_start AND i.invoice_date < :year_end';
            $params['year_start'] = sprintf('%04d-01-01', $year);
            $params['year_end'] = sprintf('%04d-01-01', $year + 1);
        }
        if ($onlyOverdue) {
            // Kein CURDATE(): die DB läuft in UTC, "heute" richtet sich nach dem deutschen Kalendertag
            $conditions[] = "i.status = 'open' AND i.due_date < :today";
            $params['today'] = BusinessClock::today()->format('Y-m-d');
        }

        $sql = self::INVOICE_SELECT;
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY i.invoice_date DESC, i.created_at DESC';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch invoices', previous: $exception);
        }

        $items = $this->loadItems(array_map(static fn(array $row): string => (string) $row['invoice_id'], $rows));

        return Invoices::from(...array_map(
            static fn(array $row): Invoice => Invoice::fromDatabase(
                $row,
                LineItems::from(...($items[(string) $row['invoice_id']] ?? [])),
            ),
            $rows,
        ));
    }

    /**
     * Sperrt die Rechnungszeile bis zum Ende der laufenden Transaktion und liefert den aktuellen Status.
     * Verhindert, dass parallele Requests (z.B. zweimal Festschreiben) auf einem veralteten Status entscheiden.
     */
    public function lockInvoiceStatus(InvoiceId $invoiceId): ?InvoiceStatus
    {
        $sql = 'SELECT i.status FROM business_invoices i WHERE i.invoice_id = :invoice_id FOR UPDATE';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['invoice_id' => $invoiceId->asString()]);
            $status = $stmt->fetchColumn();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to lock invoice', previous: $exception);
        }

        return $status !== false ? InvoiceStatus::from((string) $status) : null;
    }

    public function create(Invoice $invoice): void
    {
        $sql = <<<SQL
            INSERT INTO business_invoices
                (invoice_id, kind, status, customer_id, offer_id, deposit_invoice_id, cancels_invoice_id,
                 invoice_date, service_date, service_period_start, service_period_end, payment_term_days,
                 intro_text, closing_text, subtotal_cents, deposit_deduction_cents, total_cents)
            VALUES
                (:invoice_id, :kind, 'draft', :customer_id, :offer_id, :deposit_invoice_id, :cancels_invoice_id,
                 :invoice_date, :service_date, :service_period_start, :service_period_end, :payment_term_days,
                 :intro_text, :closing_text, :subtotal_cents, :deposit_deduction_cents, :total_cents)
        SQL;

        try {
            $this->pdo->prepare($sql)->execute($invoice->toDatabaseRow());
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to create invoice', previous: $exception);
        }

        $this->insertItems($invoice);
    }

    /** Überschreibt die Kopfdaten eines Entwurfs und ersetzt alle Positionen */
    public function update(Invoice $invoice): void
    {
        $sql = <<<SQL
            UPDATE business_invoices
            SET customer_id = :customer_id,
                invoice_date = :invoice_date,
                service_date = :service_date,
                service_period_start = :service_period_start,
                service_period_end = :service_period_end,
                payment_term_days = :payment_term_days,
                intro_text = :intro_text,
                closing_text = :closing_text,
                subtotal_cents = :subtotal_cents,
                deposit_deduction_cents = :deposit_deduction_cents,
                total_cents = :total_cents
            WHERE invoice_id = :invoice_id AND status = 'draft'
        SQL;
        $row = array_intersect_key($invoice->toDatabaseRow(), array_flip(self::UPDATABLE_COLUMNS));

        try {
            $this->pdo->prepare($sql)->execute($row);
            $this->pdo->prepare('DELETE FROM business_invoice_items WHERE invoice_id = :invoice_id')
                ->execute(['invoice_id' => $invoice->getInvoiceId()->asString()]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to update invoice', previous: $exception);
        }

        $this->insertItems($invoice);
    }

    /** Nur Entwürfe; Positionen fallen per FK-CASCADE mit weg */
    public function delete(InvoiceId $invoiceId): void
    {
        $sql = "DELETE FROM business_invoices WHERE invoice_id = :invoice_id AND status = 'draft'";

        try {
            $this->pdo->prepare($sql)->execute(['invoice_id' => $invoiceId->asString()]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to delete invoice', previous: $exception);
        }
    }

    /**
     * Schreibt die Rechnung fest: Nummer, Fälligkeit, eingefrorene Kunden-/Absenderdaten, PDF-Key und
     * Folgestatus (open bzw. issued für Stornos, siehe InvoiceKind::getFinalizedStatus).
     *
     * @param string $dueDate Y-m-d
     * @param array<string, mixed> $customerSnapshot
     * @param array<string, mixed> $issuerSnapshot
     */
    public function markFinalized(
        InvoiceId $invoiceId,
        DocumentNumber $invoiceNumber,
        string $dueDate,
        array $customerSnapshot,
        array $issuerSnapshot,
        string $pdfObjectKey,
        InvoiceStatus $status,
    ): void {
        $sql = <<<SQL
            UPDATE business_invoices
            SET status = :status,
                invoice_number = :invoice_number,
                due_date = :due_date,
                customer_snapshot = :customer_snapshot,
                issuer_snapshot = :issuer_snapshot,
                pdf_object_key = :pdf_object_key
            WHERE invoice_id = :invoice_id AND status = 'draft'
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'invoice_id' => $invoiceId->asString(),
                'status' => $status->value,
                'invoice_number' => $invoiceNumber->asString(),
                'due_date' => $dueDate,
                'customer_snapshot' => json_encode($customerSnapshot, JSON_THROW_ON_ERROR),
                'issuer_snapshot' => json_encode($issuerSnapshot, JSON_THROW_ON_ERROR),
                'pdf_object_key' => $pdfObjectKey,
            ]);
        } catch (PDOException | JsonException $exception) {
            throw new ApiDatabaseException('Failed to mark invoice as finalized', previous: $exception);
        }

        // Status wechselt immer von draft auf open/issued, daher ist genau eine Zeile betroffen
        if ($stmt->rowCount() !== 1) {
            throw new ApiDatabaseException('Invoice was not a draft while finalizing it');
        }
    }

    private function insertItems(Invoice $invoice): void
    {
        $sql = <<<SQL
            INSERT INTO business_invoice_items
                (item_id, invoice_id, position, description, quantity_hundredths, unit, unit_price_cents,
                 line_total_cents)
            VALUES
                (:item_id, :invoice_id, :position, :description, :quantity_hundredths, :unit, :unit_price_cents,
                 :line_total_cents)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($invoice->getItems() as $item) {
                $data = $item->toArray();
                $stmt->execute([
                    'item_id' => Uuid::uuid4()->toString(),
                    'invoice_id' => $invoice->getInvoiceId()->asString(),
                    'position' => $item->getPosition(),
                    'description' => $data['description'],
                    'quantity_hundredths' => $item->getQuantity()->asHundredths(),
                    'unit' => $data['unit'],
                    'unit_price_cents' => $item->getUnitPrice()->asCents(),
                    'line_total_cents' => $item->getLineTotal()->asCents(),
                ]);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to save invoice items', previous: $exception);
        }
    }

    /**
     * @param list<string> $invoiceIds
     * @return array<string, list<LineItem>> Positionen je Rechnungs-ID, nach Position sortiert
     */
    private function loadItems(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($invoiceIds), '?'));
        $sql = self::ITEM_SELECT . " WHERE it.invoice_id IN ($placeholders) ORDER BY it.invoice_id, it.position";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($invoiceIds);
            $items = [];
            foreach ($stmt as $row) {
                $items[(string) $row['invoice_id']][] = LineItem::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch invoice items', previous: $exception);
        }

        return $items;
    }
}
