<?php

namespace LukaLtaApi\Value\WebTracking\Tracking\Events;

use LukaLtaApi\Value\WebTracking\Tracking\AbstractTrackingPayload;
use LukaLtaApi\Value\WebTracking\Tracking\EventType;
use LukaLtaApi\Value\WebTracking\Tracking\Properties;
use LukaLtaApi\Value\WebTracking\Tracking\ScreenDimensions;

class PageviewPayload extends AbstractTrackingPayload
{
    private const string TYPE = 'pageview';
    private ?string $eventName;
    private ?Properties $properties;

    public static function fromPayload(array $payload): AbstractTrackingPayload
    {
        $screenDimensions = null;
        if (isset($payload['screenWidth']) && isset($payload['screenHeight'])) {
            $screenDimensions = ScreenDimensions::from($payload['screenWidth'], $payload['screenHeight']);
        }

        $instance = new self(
            $payload['site_id'],
            EventType::from(self::TYPE),
            isset($payload['hostname']) ? $payload['hostname'] : null,
            isset($payload['pathname']) ? $payload['pathname'] : null,
            $payload['querystring'] ?? null,
            $screenDimensions,
            isset($payload['language']) ? $payload['language'] : null,
            isset($payload['page_title']) ? $payload['page_title'] : null,
            isset($payload['referrer']) ? $payload['referrer'] : null,
            isset($payload['user_id']) ? $payload['user_id'] : null,
            isset($payload['ip_address']) ? $payload['ip_address'] : null,
            isset($payload['user_agent']) ? $payload['user_agent'] : null,
        );

        $instance->eventName = isset($payload['event_name']) ? $payload['event_name'] : null;
        $instance->properties = isset($payload['properties']) ? $payload['properties'] : null;

        return $instance;
    }

    private function setEventName(?string $eventName): void
    {
        $this->eventName = $eventName;
    }

    private function setProperties(?Properties $properties): void
    {
        $this->properties = $properties;
    }
}
