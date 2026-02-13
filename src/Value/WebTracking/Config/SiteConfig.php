<?php

namespace LukaLtaApi\Value\WebTracking\Config;

class SiteConfig
{
    private function __construct(
        private readonly bool $webVitals,
        private readonly bool $trackErrors,
        private readonly bool $trackOutbound,
        private readonly bool $trackUrlParams,
        private readonly bool $trackInitialPageView,
        private readonly bool $trackSpaNavigation,
        private readonly bool $trackButtonClicks,
        private readonly bool $trackCopy,
        private readonly bool $trackFormInteractions,
    ) {
    }

    public static function from(
        bool $webVitals,
        bool $trackErrors,
        bool $trackOutbound,
        bool $trackUrlParams,
        bool $trackInitialPageView,
        bool $trackSpaNavigation,
        bool $trackButtonClicks,
        bool $trackCopy,
        bool $trackFormInteractions,
    ): self {
        return new self(
            $webVitals,
            $trackErrors,
            $trackOutbound,
            $trackUrlParams,
            $trackInitialPageView,
            $trackSpaNavigation,
            $trackButtonClicks,
            $trackCopy,
            $trackFormInteractions,
        );
    }

    public function toArray(): array
    {
        return [
            'webVitals' => $this->webVitals,
            'trackErrors' => $this->trackErrors,
            'trackOutbound' => $this->trackOutbound,
            'trackUrlParams' => $this->trackUrlParams,
            'trackInitialPageView' => $this->trackInitialPageView,
            'trackSpaNavigation' => $this->trackSpaNavigation,
            'trackButtonClicks' => $this->trackButtonClicks,
            'trackCopy' => $this->trackCopy,
            'trackFormInteractions' => $this->trackFormInteractions,
        ];
    }

    public function isWebVitals(): bool
    {
        return $this->webVitals;
    }

    public function isTrackErrors(): bool
    {
        return $this->trackErrors;
    }

    public function isTrackOutbound(): bool
    {
        return $this->trackOutbound;
    }

    public function isTrackUrlParams(): bool
    {
        return $this->trackUrlParams;
    }

    public function isTrackInitialPageView(): bool
    {
        return $this->trackInitialPageView;
    }

    public function isTrackSpaNavigation(): bool
    {
        return $this->trackSpaNavigation;
    }

    public function isTrackButtonClicks(): bool
    {
        return $this->trackButtonClicks;
    }

    public function isTrackCopy(): bool
    {
        return $this->trackCopy;
    }

    public function isTrackFormInteractions(): bool
    {
        return $this->trackFormInteractions;
    }
}
