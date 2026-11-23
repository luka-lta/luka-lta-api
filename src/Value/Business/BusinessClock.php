<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use DateTimeImmutable;
use DateTimeZone;
use LukaLtaApi\Exception\ApiValidationException;

/**
 * Datum für Belege. Angebots-/Rechnungsdatum und das Jahr im Nummernkreis richten sich nach dem deutschen
 * Kalendertag; der Container läuft in UTC, zwischen 0 und 1 bzw. 2 Uhr wäre "heute" sonst noch gestern.
 */
class BusinessClock
{
    /** Zeitzone des Kleinunternehmens */
    private const string TIMEZONE = 'Europe/Berlin';

    private const string DATE_FORMAT = 'Y-m-d';

    /** Monat und Tag des 1. Januar im DATE_FORMAT */
    private const string FIRST_DAY_OF_YEAR_SUFFIX = '-01-01';

    public static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today', new DateTimeZone(self::TIMEZONE));
    }

    /**
     * Belegdatum beim Festschreiben bzw. Versenden: nicht nach heute und nicht vor dem 1. Januar des laufenden
     * Jahres. Das Nummernjahr kommt aus dem Belegdatum; ein Datum in einem anderen Jahr würde die fortlaufende
     * Nummerierung dieses Jahres unterlaufen. Verglichen werden Kalendertage, nicht Zeitpunkte.
     */
    public static function assertIssuableDate(DateTimeImmutable $documentDate, DocumentPrefix $prefix): void
    {
        $today = self::today();
        $date = $documentDate->format(self::DATE_FORMAT);
        if ($date > $today->format(self::DATE_FORMAT)) {
            throw new ApiValidationException(sprintf('%s date must not be in the future', $prefix->getLabel()), 400);
        }
        if ($date < $today->format('Y') . self::FIRST_DAY_OF_YEAR_SUFFIX) {
            throw new ApiValidationException(sprintf('%s date must be in the current year', $prefix->getLabel()), 400);
        }
    }
}
