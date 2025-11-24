<?php

namespace LukaLtaApi\Value\WebTracking\Tracking;

use LukaLtaApi\Exception\ApiInvalidArgumentException;

class ScreenDimensions
{
    private function __construct(
        private readonly int $width,
        private readonly int $height,
    ) {
        if ($width <= 0 || $height <= 0) {
            throw new ApiInvalidArgumentException('Width and height must be a positive integer');
        }
    }

    public static function from(
        int $width,
        int $height
    ): self {
        return new self($width, $height);
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getAspectRatio(): float
    {
        return $this->width / $this->height;
    }
}
