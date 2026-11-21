<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

use DateTimeImmutable;
use JsonException;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Exception\ApiValidationException;

/** Gemeinsames Parsen von Belegfeldern (Angebote, Rechnungen) aus Request und Datenbank */
class DocumentFieldParser
{
    /** Einleitungs-/Schlusstext sind TEXT-Felder (65535 Byte), Zeichenlimit mit Puffer für Multibyte */
    private const int MAX_TEXT_LENGTH = 10000;

    public const string DATE_FORMAT = 'Y-m-d';

    /**
     * Leere Strings werden zu null.
     *
     * @param array<string, mixed> $data
     */
    public static function optionalText(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new ApiValidationException(sprintf('%s must be a string', $key), 400);
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > self::MAX_TEXT_LENGTH) {
            throw new ApiValidationException(
                sprintf('%s must be at most %d characters', $key, self::MAX_TEXT_LENGTH),
                400,
            );
        }

        return $value;
    }

    /**
     * Kalendertag ohne Uhrzeit (00:00 in der Standard-Zeitzone). Vergleiche deshalb immer über
     * format(DATE_FORMAT), nie über die Zeitpunkte.
     *
     * @param array<string, mixed> $data
     */
    public static function optionalDate(array $data, string $key): ?DateTimeImmutable
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new ApiValidationException(sprintf('%s must be a date (YYYY-MM-DD)', $key), 400);
        }

        $date = DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, $value);
        if ($date === false || $date->format(self::DATE_FORMAT) !== $value) {
            throw new ApiValidationException(sprintf('%s must be a date (YYYY-MM-DD)', $key), 400);
        }

        return $date;
    }

    /** @return ?array<mixed> */
    public static function decodeJson(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        try {
            $decoded = json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ApiDatabaseException('Invalid snapshot JSON', previous: $exception);
        }
        if (!is_array($decoded)) {
            throw new ApiDatabaseException('Invalid snapshot JSON');
        }

        return $decoded;
    }
}
