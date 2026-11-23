<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use DateTimeImmutable;
use LukaLtaApi\Exception\BusinessDocumentStateException;

/**
 * Stand der Anzahlungsrechnung, auf die eine Schlussrechnung verweist. Entscheidet über die Sperre der
 * Schlussrechnung und die Höhe des Abzugs.
 */
class DepositInvoiceState
{
    private function __construct(
        private readonly InvoiceId $invoiceId,
        private readonly InvoiceStatus $status,
        private readonly ?string $invoiceNumber,
        private readonly ?DateTimeImmutable $invoiceDate,
        private readonly Money $total,
    ) {
    }

    public static function create(
        InvoiceId $invoiceId,
        InvoiceStatus $status,
        ?string $invoiceNumber,
        ?DateTimeImmutable $invoiceDate,
        Money $total,
    ): self {
        return new self($invoiceId, $status, $invoiceNumber, $invoiceDate, $total);
    }

    public static function fromInvoice(Invoice $deposit): self
    {
        return new self(
            $deposit->getInvoiceId(),
            $deposit->getStatus(),
            $deposit->getInvoiceNumber()?->asString(),
            $deposit->getInvoiceDate(),
            $deposit->getTotal(),
        );
    }

    public function getInvoiceId(): InvoiceId
    {
        return $this->invoiceId;
    }

    public function getStatus(): InvoiceStatus
    {
        return $this->status;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function getInvoiceDate(): ?DateTimeImmutable
    {
        return $this->invoiceDate;
    }

    /**
     * Grund, warum die Schlussrechnung (noch) nicht festgeschrieben werden darf: Anzahlung ist Entwurf oder
     * offen. Bezahlt oder storniert sperrt nicht.
     */
    public function getFinalizeBlocker(): ?BusinessDocumentStateException
    {
        if ($this->status === InvoiceStatus::Draft || $this->invoiceNumber === null) {
            return BusinessDocumentStateException::depositNotFinalized();
        }
        if ($this->status === InvoiceStatus::Open) {
            return BusinessDocumentStateException::depositUnpaid($this->invoiceNumber);
        }

        return null;
    }

    /** Abzug auf der Schlussrechnung: Gesamtbetrag der Anzahlung, entfällt bei stornierter Anzahlung */
    public function getDeduction(): Money
    {
        if ($this->status === InvoiceStatus::Cancelled) {
            return Money::fromCents(0);
        }

        return $this->total;
    }
}
