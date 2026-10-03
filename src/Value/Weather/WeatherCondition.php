<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Weather;

/**
 * Maps Open-Meteo's numeric WMO weather codes to a normalized German condition
 * label and icon key. Lives on the Value layer (not the provider) because every
 * future provider still needs to produce one of these same labels — the UI
 * never sees a raw provider code, and every label used in the app comes from
 * exactly this one place.
 */
class WeatherCondition
{
    private function __construct(
        private readonly string $label,
        private readonly string $icon,
    ) {
    }

    public static function fromWmoCode(int $code, bool $isDay): self
    {
        return match (true) {
            $code === 0 => new self('Klar', $isDay ? 'clear-day' : 'clear-night'),
            $code === 1 => new self('Überwiegend klar', $isDay ? 'clear-day' : 'clear-night'),
            $code === 2 => new self('Teilweise bewölkt', $isDay ? 'partly-cloudy-day' : 'partly-cloudy-night'),
            $code === 3 => new self('Bedeckt', 'cloudy'),
            in_array($code, [45, 48], true) => new self('Nebel', 'fog'),
            in_array($code, [51, 53, 55, 56, 57], true) => new self('Nieselregen', 'drizzle'),
            in_array($code, [61, 63, 65, 66, 67], true) => new self('Regen', 'rain'),
            in_array($code, [71, 73, 75, 77, 85, 86], true) => new self('Schnee', 'snow'),
            in_array($code, [80, 81, 82], true) => new self('Regenschauer', 'rain'),
            in_array($code, [95, 96, 99], true) => new self('Gewitter', 'thunderstorm'),
            default => new self('Unbekannt', 'unknown'),
        };
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }
}
