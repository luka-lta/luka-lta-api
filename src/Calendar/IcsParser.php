<?php

declare(strict_types=1);

namespace LukaLtaApi\Calendar;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Minimal RFC 5545 VEVENT reader — just the fields the dashboard shows.
 *
 * Known limitation: recurring events (RRULE) are skipped entirely rather than
 * expanded. Expanding recurrence correctly (exceptions, until-dates, timezones)
 * needs a real recurrence-rule engine; until one is added, a recurring meeting
 * simply won't appear rather than showing as a wrong one-off occurrence.
 */
class IcsParser
{
    /**
     * @return array<int, array{
     *     uid: string, title: string, startsAt: DateTimeImmutable, endsAt: DateTimeImmutable,
     *     isAllDay: bool, location: ?string, description: ?string, attendees: string[], url: ?string
     * }>
     */
    public function parse(string $ics): array
    {
        $lines = $this->unfold($ics);
        $events = [];
        $current = null;

        foreach ($lines as $line) {
            if ($line === 'BEGIN:VEVENT') {
                $current = [];
                continue;
            }

            if ($line === 'END:VEVENT') {
                if ($current !== null && !isset($current['RRULE'])) {
                    $event = $this->toEvent($current);
                    if ($event !== null) {
                        $events[] = $event;
                    }
                }
                $current = null;
                continue;
            }

            if ($current === null) {
                continue;
            }

            [$name, $params, $value] = $this->splitLine($line);
            $current[$name] = ['params' => $params, 'value' => $value];

            if ($name === 'ATTENDEE') {
                $current['ATTENDEE_LIST'][] = $params['CN'] ?? $value;
            }
        }

        return $events;
    }

    /** @return string[] */
    private function unfold(string $ics): array
    {
        $raw = preg_split('/\r\n|\n|\r/', $ics) ?: [];
        $lines = [];

        foreach ($raw as $line) {
            if ($line === '') {
                continue;
            }

            if (($line[0] === ' ' || $line[0] === "\t") && !empty($lines)) {
                $lines[count($lines) - 1] .= substr($line, 1);
                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /** @return array{0: string, 1: array<string,string>, 2: string} */
    private function splitLine(string $line): array
    {
        $colonPos = strpos($line, ':');
        if ($colonPos === false) {
            return [$line, [], ''];
        }

        $head = substr($line, 0, $colonPos);
        $value = substr($line, $colonPos + 1);
        $parts = explode(';', $head);
        $name = array_shift($parts);

        $params = [];
        foreach ($parts as $part) {
            [$key, $val] = array_pad(explode('=', $part, 2), 2, '');
            $params[strtoupper($key)] = $val;
        }

        return [strtoupper($name), $params, $value];
    }

    private function toEvent(array $fields): ?array
    {
        if (!isset($fields['UID'], $fields['SUMMARY'], $fields['DTSTART'])) {
            return null;
        }

        $isAllDay = ($fields['DTSTART']['params']['VALUE'] ?? '') === 'DATE';
        $start = $this->parseDateTime($fields['DTSTART']['value'], $fields['DTSTART']['params']);

        if ($start === null) {
            return null;
        }

        $end = isset($fields['DTEND'])
            ? $this->parseDateTime($fields['DTEND']['value'], $fields['DTEND']['params'])
            : $start->modify($isAllDay ? '+1 day' : '+1 hour');

        return [
            'uid' => $fields['UID']['value'],
            'title' => $this->unescape($fields['SUMMARY']['value']),
            'startsAt' => $start,
            'endsAt' => $end ?? $start,
            'isAllDay' => $isAllDay,
            'location' => isset($fields['LOCATION']) ? $this->unescape($fields['LOCATION']['value']) : null,
            'description' => isset($fields['DESCRIPTION']) ? $this->unescape($fields['DESCRIPTION']['value']) : null,
            'attendees' => $fields['ATTENDEE_LIST'] ?? [],
            'url' => $fields['URL']['value'] ?? null,
        ];
    }

    private function parseDateTime(string $value, array $params): ?DateTimeImmutable
    {
        try {
            if (($params['VALUE'] ?? '') === 'DATE') {
                return new DateTimeImmutable($value);
            }

            if (str_ends_with($value, 'Z')) {
                return new DateTimeImmutable($value, new DateTimeZone('UTC'));
            }

            if (isset($params['TZID'])) {
                return new DateTimeImmutable($value, new DateTimeZone($params['TZID']));
            }

            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function unescape(string $value): string
    {
        return str_replace(['\\n', '\\,', '\\;', '\\\\'], ["\n", ',', ';', '\\'], $value);
    }
}
