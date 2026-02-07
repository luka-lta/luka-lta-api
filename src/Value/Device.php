<?php

declare(strict_types=1);

namespace LukaLtaApi\Value;

use LukaLtaApi\Value\WebTracking\Tracking\ScreenDimensions;

class Device
{
    public function __construct(
        private readonly string $deviceType,
    ) {
    }

    public static function fromScreenDimension(ScreenDimensions $screenDimension): self
    {
        $largerDimension = max($screenDimension->getWidth(), $screenDimension->getHeight());
        $smallerDimension = min($screenDimension->getWidth(), $screenDimension->getHeight());

        if ($largerDimension >= 1280) {
            return new self('Desktop');
        }

        if ($largerDimension >= 800 && $smallerDimension >= 600) {
            return new self('Tablet');
        }

        return new self('Mobile');
    }

    public function getDeviceType(): string
    {
        return $this->deviceType;
    }
}
