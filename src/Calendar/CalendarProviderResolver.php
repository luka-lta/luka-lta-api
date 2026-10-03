<?php

declare(strict_types=1);

namespace LukaLtaApi\Calendar;

use LukaLtaApi\Exception\ApiValidationException;

/**
 * Picks the right CalendarProviderInterface for a source's `type`. The only
 * place that knows which concrete provider classes exist — CalendarService
 * never branches on provider type itself.
 */
class CalendarProviderResolver
{
    public function __construct(
        private readonly IcsCalendarProvider $icsProvider,
    ) {
    }

    public function resolve(string $type): CalendarProviderInterface
    {
        return match ($type) {
            'ics' => $this->icsProvider,
            default => throw new ApiValidationException("Unsupported calendar source type: {$type}", 400),
        };
    }
}
