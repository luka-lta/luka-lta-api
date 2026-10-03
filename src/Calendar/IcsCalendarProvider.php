<?php

declare(strict_types=1);

namespace LukaLtaApi\Calendar;

use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use LukaLtaApi\Exception\ApiHttpException;
use LukaLtaApi\Value\Calendar\CalendarEvent;
use LukaLtaApi\Value\Calendar\CalendarSource;

/**
 * Reads a plain .ics URL — works with any calendar that can publish one (Google
 * Calendar's "secret address in iCal format", Outlook's published calendar link,
 * Apple iCloud, Fastmail, ...). No OAuth, no app registration.
 */
class IcsCalendarProvider implements CalendarProviderInterface
{
    private readonly Client $client;

    public function __construct(
        private readonly IcsParser $parser,
    ) {
        $this->client = new Client(['timeout' => 10]);
    }

    public function fetchEvents(CalendarSource $source, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        try {
            $response = $this->client->get($source->getUrl());
        } catch (GuzzleException $exception) {
            throw new ApiHttpException(
                "Failed to fetch calendar '{$source->getName()}'.",
                previous: $exception,
            );
        }

        $parsed = $this->parser->parse($response->getBody()->getContents());

        $events = [];
        foreach ($parsed as $fields) {
            if ($fields['endsAt'] < $from || $fields['startsAt'] > $to) {
                continue;
            }

            $events[] = CalendarEvent::create(
                $fields['uid'],
                (int) $source->getId(),
                $source->getName(),
                $source->getColor(),
                $fields['title'],
                $fields['startsAt'],
                $fields['endsAt'],
                $fields['isAllDay'],
                $fields['location'],
                $fields['description'],
                $fields['attendees'],
                $fields['url'],
            );
        }

        return $events;
    }
}
