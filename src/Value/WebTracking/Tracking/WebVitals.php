<?php

namespace LukaLtaApi\Value\WebTracking\Tracking;

class WebVitals
{
    private function __construct(
        private readonly ?float $lcp,
        private readonly ?float $cls,
        private readonly ?float $inp,
        private readonly ?float $fcp,
        private readonly ?float $ttfb,
    ) {
    }

    public function getLcp(): ?float
    {
        return $this->lcp;
    }

    public function getCls(): ?float
    {
        return $this->cls;
    }

    public function getInp(): ?float
    {
        return $this->inp;
    }

    public function getFcp(): ?float
    {
        return $this->fcp;
    }

    public function getTtfb(): ?float
    {
        return $this->ttfb;
    }

    public function toArray(): array
    {
        return [
            'lcp' => $this->lcp,
            'cls' => $this->cls,
            'inp' => $this->inp,
            'fcp' => $this->fcp,
            'ttfb' => $this->ttfb
        ];
    }
}
