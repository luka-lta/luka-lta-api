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
    ) {
    }

    public static function from(
        bool $webVitals,
        bool $trackErrors,
        bool $trackOutbound,
        bool $trackUrlParams,
        bool $trackInitialPageView,
        bool $trackSpaNavigation,
    ): self {
        return new self(
            $webVitals,
            $trackErrors,
            $trackOutbound,
            $trackUrlParams,
            $trackInitialPageView,
            $trackSpaNavigation
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
}