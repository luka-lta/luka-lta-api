<?php

declare(strict_types=1);

namespace LukaLtaApi\Calendar;

use DateTimeImmutable;
use LukaLtaApi\Value\Calendar\CalendarEvent;
use LukaLtaApi\Value\Calendar\CalendarSource;

/**
 * One implementation per calendar backend (ICS feed today; Google/Outlook could
 * follow later as their own class here). CalendarService and the UI depend only
 * on this interface and on CalendarEvent — never on a provider's raw format.
 */
interface CalendarProviderInterface
{
    /** @return CalendarEvent[] */
    public function fetchEvents(CalendarSource $source, DateTimeImmutable $from, DateTimeImmutable $to): array;
}
