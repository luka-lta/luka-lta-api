<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use DateTimeImmutable;
use JsonException;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Repository\Traits\TransactionTrait;
use LukaLtaApi\Value\Business\BusinessClock;
use LukaLtaApi\Value\Business\CustomerId;
use LukaLtaApi\Value\Business\DepositInvoiceState;
use LukaLtaApi\Value\Business\DocumentNumber;
use LukaLtaApi\Value\Business\Invoice;
use LukaLtaApi\Value\Business\InvoiceId;
use LukaLtaApi\Value\Business\Invoices;
use LukaLtaApi\Value\Business\InvoiceStatus;
use LukaLtaApi\Value\Business\LineItem;
use LukaLtaApi\Value\Business\LineItems;
use LukaLtaApi\Value\Business\Money;
use LukaLtaApi\Value\Business\OfferId;
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
            di.status AS deposit_invoice_status,
            di.total_cents AS deposit_invoice_total_cents,
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

        // Vor dem Ersetzen der Positionen sicherstellen, dass die Zeile (noch) ein Entwurf ist. rowCount() des
        // UPDATE taugt dafür nicht: MySQL zählt nur tatsächlich geänderte Zeilen, ein PATCH ohne Änderung ergäbe 0.
        if ($this->lockInvoiceStatus($invoice->getInvoiceId()) !== InvoiceStatus::Draft) {
            throw new ApiDatabaseException('Invoice was not a draft while updating it');
        }

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

    /**
     * Bezahlt markieren (paidAt gesetzt: open → paid) oder Zahlung zurücknehmen (null: paid → open).
     * Der Aufrufer hält die Zeilensperre und hat den Status geprüft; der Statuswechsel garantiert rowCount 1.
     *
     * @param ?string $paidAt Y-m-d
     */
    public function setPaid(InvoiceId $invoiceId, ?string $paidAt): void
    {
        $sql = $paidAt !== null
            ? "UPDATE business_invoices SET status = 'paid', paid_at = :paid_at "
                . "WHERE invoice_id = :invoice_id AND status = 'open'"
            : "UPDATE business_invoices SET status = 'open', paid_at = NULL "
                . "WHERE invoice_id = :invoice_id AND status = 'paid'";
        $params = ['invoice_id' => $invoiceId->asString()];
        if ($paidAt !== null) {
            $params['paid_at'] = $paidAt;
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to update invoice payment', previous: $exception);
        }

        if ($stmt->rowCount() !== 1) {
            throw new ApiDatabaseException('Invoice status changed while updating its payment');
        }
    }

    /**
     * Statuswechsel nur ausgehend vom erwarteten Status (z.B. open/paid → cancelled); paid_at bleibt unberührt.
     * Der Aufrufer hält die Zeilensperre; da sich der Status ändert, ist genau eine Zeile betroffen.
     */
    public function setStatus(InvoiceId $invoiceId, InvoiceStatus $expectedStatus, InvoiceStatus $newStatus): void
    {
        if ($expectedStatus === $newStatus) {
            throw new ApiDatabaseException('Invoice status must change');
        }

        $sql = <<<SQL
            UPDATE business_invoices
            SET status = :new_status
            WHERE invoice_id = :invoice_id AND status = :expected_status
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'invoice_id' => $invoiceId->asString(),
                'expected_status' => $expectedStatus->value,
                'new_status' => $newStatus->value,
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to update invoice status', previous: $exception);
        }

        if ($stmt->rowCount() !== 1) {
            throw new ApiDatabaseException('Invoice status changed while updating it');
        }
    }

    /**
     * Gibt es eine festgeschriebene, nicht stornierte Schlussrechnung, die diese Anzahlung abzieht?
     * Dann darf die Anzahlung weder storniert noch auf unbezahlt zurückgesetzt werden.
     */
    public function hasActiveFinalInvoiceForDeposit(InvoiceId $depositInvoiceId): bool
    {
        $sql = <<<SQL
            SELECT 1
            FROM business_invoices f
            WHERE f.deposit_invoice_id = :deposit_invoice_id
                AND f.kind = 'final'
                AND f.status NOT IN ('draft', 'cancelled')
            LIMIT 1
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['deposit_invoice_id' => $depositInvoiceId->asString()]);
            $isReferenced = $stmt->fetchColumn() !== false;
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to check final invoices', previous: $exception);
        }

        return $isReferenced;
    }

    /**
     * Aktive Anzahlungsrechnung eines Angebots: Art deposit, jeder Status außer cancelled (also auch Entwurf).
     * Stornos übernehmen offer_id ihres Originals, daher der Filter auf die Art.
     */
    public function loadActiveDepositForOffer(OfferId $offerId): ?Invoice
    {
        $sql = <<<SQL
            SELECT d.invoice_id
            FROM business_invoices d
            WHERE d.offer_id = :offer_id
                AND d.kind = 'deposit'
                AND d.status <> 'cancelled'
            ORDER BY d.created_at DESC
            LIMIT 1
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['offer_id' => $offerId->asString()]);
            $depositInvoiceId = $stmt->fetchColumn();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch deposit invoice', previous: $exception);
        }

        if ($depositInvoiceId === false) {
            return null;
        }

        return $this->loadInvoice(InvoiceId::fromString((string) $depositInvoiceId));
    }

    /** Gibt es zum Angebot eine nicht stornierte Schlussrechnung (auch Entwurf)? */
    public function hasActiveFinalInvoiceForOffer(OfferId $offerId): bool
    {
        $sql = <<<SQL
            SELECT 1
            FROM business_invoices f
            WHERE f.offer_id = :offer_id
                AND f.kind = 'final'
                AND f.status <> 'cancelled'
            LIMIT 1
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['offer_id' => $offerId->asString()]);
            $hasFinalInvoice = $stmt->fetchColumn() !== false;
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to check final invoices', previous: $exception);
        }

        return $hasFinalInvoice;
    }

    /** Verweist irgendeine Rechnung (z.B. ein Schlussrechnungs-Entwurf) auf diese Anzahlung? */
    public function isDepositReferenced(InvoiceId $depositInvoiceId): bool
    {
        $sql = <<<SQL
            SELECT 1
            FROM business_invoices f
            WHERE f.deposit_invoice_id = :deposit_invoice_id
            LIMIT 1
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['deposit_invoice_id' => $depositInvoiceId->asString()]);
            $isReferenced = $stmt->fetchColumn() !== false;
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to check deposit references', previous: $exception);
        }

        return $isReferenced;
    }

    /**
     * Sperrt die Zeile der Anzahlung (FOR UPDATE) und liest ihren aktuellen Stand. Als sperrendes Lesen
     * sieht es den zuletzt committeten Stand, nicht den Snapshot der Transaktion — entscheidend, wenn
     * parallel bezahlt, zurückgesetzt oder storniert wird.
     */
    public function lockDepositInvoice(InvoiceId $depositInvoiceId): ?DepositInvoiceState
    {
        $sql = <<<SQL
            SELECT d.invoice_id, d.status, d.invoice_number, d.invoice_date, d.total_cents
            FROM business_invoices d
            WHERE d.invoice_id = :invoice_id AND d.kind = 'deposit'
            FOR UPDATE
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['invoice_id' => $depositInvoiceId->asString()]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to lock deposit invoice', previous: $exception);
        }

        if ($row === false) {
            return null;
        }

        return DepositInvoiceState::create(
            InvoiceId::fromString((string) $row['invoice_id']),
            InvoiceStatus::from((string) $row['status']),
            $row['invoice_number'],
            new DateTimeImmutable((string) $row['invoice_date']),
            Money::fromCents((int) $row['total_cents']),
        );
    }

    /**
     * Schreibt Anzahlungsabzug und Gesamtbetrag eines Entwurfs. Der Aufrufer hält die Zeilensperre; ohne
     * Änderung meldet MySQL 0 Zeilen, daher keine rowCount-Prüfung (markFinalized prüft danach den Status).
     */
    public function updateDepositDeduction(InvoiceId $invoiceId, Money $depositDeduction, Money $total): void
    {
        $sql = <<<SQL
            UPDATE business_invoices
            SET deposit_deduction_cents = :deposit_deduction_cents,
                total_cents = :total_cents
            WHERE invoice_id = :invoice_id AND status = 'draft'
        SQL;

        try {
            $this->pdo->prepare($sql)->execute([
                'invoice_id' => $invoiceId->asString(),
                'deposit_deduction_cents' => $depositDeduction->asCents(),
                'total_cents' => $total->asCents(),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to update deposit deduction', previous: $exception);
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
