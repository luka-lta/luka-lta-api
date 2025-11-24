<?php

namespace LukaLtaApi\Value\WebTracking\Tracking;

use LukaLtaApi\Value\WebTracking\Tracking\Events\PageviewPayload;

class TrackingBatch
{
    private const int BATCH_SIZE = 5000;

    public function __construct(
        private readonly array $events
    ) {
    }

    public static function from(array $payloads): self
    {
        $events = [];

        foreach ($payloads as $payload) {
            $events[] = self::createValueObject($payload);
        }

        return new self($events);
    }

    private static function createValueObject(array $payload): AbstractTrackingPayload
    {
        $type = $payload['type'];

        return match ($type) {
            EventType::PAGEVIEW->value => PageviewPayload::fromPayload($payload),
        };
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function count(): int
    {
        return count($this->events);
    }
}
