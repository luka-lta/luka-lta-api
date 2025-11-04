<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Tracking;

class UserAgent
{
    private function __construct(
        private readonly string $rawUserAgent,
        private readonly ?string $os,
        private readonly ?string $device,
    ) {
    }

    public static function fromUserAgent(string $userAgent): self
    {
        return new self(
            $userAgent,
            self::detectOs($userAgent),
            self::detectDevice($userAgent)
        );
    }

    public static function from(string $rawUserAgent, ?string $os, ?string $device): self
    {
        return new self(
            $rawUserAgent,
            $os,
            $device
        );
    }

    private static function detectOs(string $userAgent): string
    {
        $patterns = [
            'Android' => '/Android/i',
            'iOS' => '/iPhone|iPad/i',
            'Windows' => '/Windows/i',
            'macOS' => '/Macintosh/i',
            'Linux' => '/Linux/i',
        ];

        foreach ($patterns as $os => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $os;
            }
        }

        return 'Unknown';
    }

    private static function detectDevice(string $userAgent): string
    {
        // Tablet vor Mobile prüfen, da Tablets oft auch "Mobile" enthalten
        if (preg_match('/iPad|Tablet/i', $userAgent)) {
            return 'Tablet';
        }

        if (preg_match('/Mobile/i', $userAgent)) {
            return 'Mobile';
        }

        return 'Desktop';
    }

    public function getRawUserAgent(): string
    {
        return $this->rawUserAgent;
    }

    public function getOs(): ?string
    {
        return $this->os;
    }

    public function getDevice(): ?string
    {
        return $this->device;
    }
}
