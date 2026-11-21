<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

enum InvoiceKind: string
{
    case Standard = 'standard';
    case Deposit = 'deposit';
    case Final = 'final';
    case Cancellation = 'cancellation';

    public function getPdfTitle(): string
    {
        return match ($this) {
            self::Standard => 'Rechnung',
            self::Deposit => 'Anzahlungsrechnung',
            self::Final => 'Schlussrechnung',
            self::Cancellation => 'Stornorechnung',
        };
    }

    /** Status nach dem Festschreiben: Stornos sind nie zahlungspflichtig */
    public function getFinalizedStatus(): InvoiceStatus
    {
        return $this === self::Cancellation ? InvoiceStatus::Issued : InvoiceStatus::Open;
    }

    /** Stornos erben das Leistungsdatum vom Original (das bei Importen fehlen kann) und haben keine Fälligkeit */
    public function isCancellation(): bool
    {
        return $this === self::Cancellation;
    }
}
