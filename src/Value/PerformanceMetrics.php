<?php

declare(strict_types=1);

namespace LukaLtaApi\Value;

class PerformanceMetrics
{
    private function __construct(
        private readonly ?float $lcp = null,
        private readonly ?float $cls = null,
        private readonly ?float $fcp = null,
        private readonly ?int $ttfb = null,
    ) {
    }

    public static function from(
        ?float $lcp,
        ?float $cls,
        ?float $fcp,
        ?int $ttfb,
    ): self {
        return new self(
            $lcp,
            $cls,
            $fcp,
            $ttfb,
        );
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            isset($payload['lcp']) ? (float)$payload['lcp'] : null,
            isset($payload['cls']) ? (float)$payload['cls'] : null,
            isset($payload['fcp']) ? (float)$payload['fcp'] : null,
            isset($payload['ttfb']) ? (int)$payload['ttfb'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'lcp' => $this?->lcp,
            'cls' => $this?->cls,
            'fcp' => $this?->fcp,
            'ttfb' => $this?->ttfb,
        ];
    }

    public function getLcp(): ?float
    {
        return $this->lcp;
    }

    public function getCls(): ?float
    {
        return $this->cls;
    }

    public function getFcp(): ?float
    {
        return $this->fcp;
    }

    public function getTtfb(): ?int
    {
        return $this->ttfb;
    }
}
