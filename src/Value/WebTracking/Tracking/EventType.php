<?php

namespace LukaLtaApi\Value\WebTracking\Tracking;

enum EventType: string
{
    case PAGEVIEW = 'pageview';
    case CUSTOM_EVENT = 'custom_event';
    case PERFORMANCE = 'performance';
    case OUTBOUND = 'outbound';
    case ERROR = 'error';

    public static function fromName(string $eventType): self
    {
        return match ($eventType) {
            'pageview' => self::PAGEVIEW,
            'custom_event' => self::CUSTOM_EVENT,
            'performance' => self::PERFORMANCE,
            'outbound' => self::OUTBOUND,
            'error' => self::ERROR,
        };
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
