<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Time;

use DateTimeImmutable;
use DateTimeZone;
use LukaLtaApi\Value\Time\Exception\InvalidDateFormatException;

final readonly class UtcDate
{
    public const string FORMAT_HUMAN = 'Y-m-d H:i:s';

    public static function from(string $format, string $value, ?DateTimeZone $inputTimeZone = null): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat($format, $value, $inputTimeZone);

        if ($date === false) {
            throw new InvalidDateFormatException($value, $format);
        }

        return $date->setTimezone(new DateTimeZone('UTC'));
    }

    public static function now(): DateTimeImmutable
    {
        $value = date(DATE_ATOM);
        $date = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);

        // Nur als Failsafe
        if ($date === false) {
            throw new InvalidDateFormatException($value, null);
        }

        return $date->setTimezone(new DateTimeZone('UTC'));
    }

    public static function parse(string $value, ?DateTimeZone $inputTimeZone = null): DateTimeImmutable
    {
        return (new DateTimeImmutable($value, $inputTimeZone))->setTimezone(new DateTimeZone('UTC'));
    }
}
