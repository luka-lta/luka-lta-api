<?php

namespace LukaLtaApi\Value\WebTracking\Tracking;

enum EventType: string
{
    case PAGEVIEW = 'pageview';
    case CUSTOM_EVENT = 'custom_event';
    case PERFORMANCE = 'performance';
    case OUTBOUND = 'outbound';
    case ERROR = 'error';
    case COPY = 'copy';
    case BUTTON_CLICK = 'button_click';
    case FORM_SUBMIT = 'form_submit';
    case INPUT_CHANGE = 'input_change';

    public static function fromName(string $eventType): self
    {
        return match ($eventType) {
            'pageview' => self::PAGEVIEW,
            'custom_event' => self::CUSTOM_EVENT,
            'performance' => self::PERFORMANCE,
            'outbound' => self::OUTBOUND,
            'error' => self::ERROR,
            'copy' => self::COPY,
            'button_click' => self::BUTTON_CLICK,
            'form_submit' => self::FORM_SUBMIT,
        };
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
