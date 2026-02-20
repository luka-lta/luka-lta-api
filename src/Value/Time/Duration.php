<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Time;

use JsonSerializable;

class Duration implements JsonSerializable
{
    private function __construct(private float $seconds)
    {}

    public static function zero(): self
    {
        return new self(0.0);
    }

    public static function fromSeconds(float $seconds): self
    {
        return new self($seconds);
    }

    public static function fromMilliseconds(float $milliseconds): self
    {
        return new self($milliseconds * 1e-3);
    }

    public static function fromMicroseconds(float $microseconds): self
    {
        return new self($microseconds * 1e-6);
    }

    public static function fromNanoseconds(int $nanoseconds): self
    {
        return new self($nanoseconds * 1e-9);
    }

    public static function max(self $value, self ...$values): self
    {
        $max = $value;

        foreach ($values as $v) {
            if ($v->seconds > $max->seconds) {
                $max = $v;
            }
        }

        return $max;
    }

    public static function merge(self ...$others): self
    {
        $sum = 0.0;

        foreach ($others as $other) {
            $sum += $other->seconds;
        }

        return new self($sum);
    }

    public function addMut(self $other): void
    {
        $this->seconds += $other->seconds;
    }

    public function sub(Duration $other): self
    {
        return self::fromSeconds($this->seconds - $other->seconds);
    }

    public function getDays(): float
    {
        return $this->seconds / 3600 / 24;
    }

    public function getHours(): float
    {
        return $this->seconds / 3600;
    }

    public function getMinutes(): float
    {
        return $this->seconds / 60;
    }

    public function getSeconds(): float
    {
        return $this->seconds;
    }

    public function getMilliseconds(): float
    {
        return $this->seconds * 1e3;
    }

    public function getMicroseconds(): float
    {
        return $this->seconds * 1e6;
    }

    public function getNanoseconds(): int
    {
        return (int)($this->seconds * 1e9);
    }

    public function jsonSerialize(): float
    {
        return $this->seconds;
    }

    /**
     * Einheit/Größenordnung mit `jsonSerialize` gleichhalten (Sekunden)!
     */
    public static function jsonDeserialize(float $seconds): self
    {
        return self::fromSeconds($seconds);
    }
}
