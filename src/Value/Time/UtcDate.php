<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Time;

use Cassandra\Duration;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonSerializable;
use LukaLtaApi\Value\Time\Exception\InvalidDateFormatException;
use Stringable;

final readonly class UtcDate implements JsonSerializable, Stringable
{
    public const string FORMAT_HUMAN = 'Y-m-d H:i:s';

    private function __construct(
        private DateTimeImmutable $date
    ) {}

    public static function now(): self
    {
        $utc  = new DateTimeZone('UTC');
        $date = new DateTimeImmutable();
        $date = $date->setTimezone($utc);

        return new self($date);
    }

    public static function createFromDateTime(DateTimeInterface $dateTime): self
    {
        $utc  = new DateTimeZone('UTC');
        $date = DateTimeImmutable::createFromInterface($dateTime);
        $date = $date->setTimezone($utc);

        return new self($date);
    }

    /**
     * @param string $format
     * @param string $dateTime
     * @param DateTimeZone|null $inputTimeZone Timezone, the dateTime given is in. null assumes the given date is in UTC
     * @return self
     *
     * @throws InvalidDateFormatException
     */
    public static function createFromFormat(string $format, string $dateTime, ?DateTimeZone $inputTimeZone = null): self
    {
        $utc = new DateTimeZone('UTC');
        $inputTimeZone ??= $utc;

        $date = DateTimeImmutable::createFromFormat($format, $dateTime, $inputTimeZone);

        if ($date === false) {
            throw InvalidDateFormatException::fromInputAndFormat($dateTime, $format);
        }

        $date = $date->setTimezone($utc);

        return new self($date);
    }

    /**
     * @param int $timestamp The UNIX timestamp (which by definition is in UTC)
     */
    public static function createFromTimestamp(int $timestamp): self
    {
        $date = new DateTimeImmutable();
        $date = $date->setTimestamp($timestamp)->setTimezone(new DateTimeZone('UTC'));

        return new self($date);
    }

    /**
     * @param string $dateTime
     * @param DateTimeZone|null $timeZone Timezone, the dateTime given is in. null assumes the given date is in UTC
     * @return self
     *
     * @throws InvalidDateFormatException
     */
    public static function parse(string $dateTime, ?DateTimeZone $timeZone = null): self
    {
        $utc = new DateTimeZone('UTC');
        $timeZone ??= $utc;

        try {
            $date = new DateTimeImmutable($dateTime, $timeZone);
        } catch (Exception $exception) {
            throw InvalidDateFormatException::fromInput($dateTime, $exception);
        }

        $date = $date->setTimezone($utc);

        return new self($date);
    }

    public function format(string $format = self::FORMAT_HUMAN): string
    {
        return $this->date->format($format);
    }

    public function atMidnight(): self
    {
        $newDate = $this->date->setTime(0, 0);
        return new self($newDate);
    }

    public function yesterday(): self
    {
        $yesterday = $this->date->modify('-1 day')->setTime(12, 0);
        return new self($yesterday);
    }

    public function atNoon(): self
    {
        return new self($this->date->setTime(12, 0));
    }

    public function add(DateInterval $interval): self
    {
        return new self($this->date->add($interval));
    }

    public function sub(DateInterval $interval): self
    {
        return new self($this->date->sub($interval));
    }

    /**
     * Berechnet die Differenz zwischen zwei UtcDates in Sekunden.
     * Eine negative Differenz ist möglich.
     */
    public function diff(self $other): Duration
    {
        $seconds = $other->getTimestamp() - $this->date->getTimestamp();
        return Duration::fromSeconds($seconds);
    }

    public function getTimestamp(): int
    {
        return $this->date->getTimestamp();
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function toString(): string
    {
        return $this->format(DATE_ATOM);
    }

    public function __toString(): string
    {
        return $this->format(DATE_ATOM);
    }

    public function equals(self $other): bool
    {
        return $this->date->format(DATE_ATOM) === $other->format(DATE_ATOM);
    }

    public function jsonSerialize(): string
    {
        return $this->date->format(DATE_ATOM);
    }

    public static function jsonDeserialize(string $json): self
    {
        return self::createFromFormat(DATE_ATOM, $json);
    }
}
