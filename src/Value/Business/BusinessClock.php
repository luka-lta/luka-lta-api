<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Datum für Belege. Angebots-/Rechnungsdatum und das Jahr im Nummernkreis richten sich nach dem deutschen
 * Kalendertag; der Container läuft in UTC, zwischen 0 und 1 bzw. 2 Uhr wäre "heute" sonst noch gestern.
 */
class BusinessClock
{
    /** Zeitzone des Kleinunternehmens */
    private const string TIMEZONE = 'Europe/Berlin';

    public static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today', new DateTimeZone(self::TIMEZONE));
    }
}
