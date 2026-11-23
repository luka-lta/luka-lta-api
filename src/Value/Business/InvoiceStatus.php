<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /** Festgeschriebene Stornorechnung: hat keinen Zahlungsstatus */
    case Issued = 'issued';
}
