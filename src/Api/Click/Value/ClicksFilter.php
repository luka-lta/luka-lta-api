<?php

namespace LukaLtaApi\Api\Click\Value;

class ClicksFilter
{
    private function __construct(
        private readonly array $marketFilter,
        private readonly array $deviceFilter,
        private readonly array $userAgentFilter, // TODO: Implement
        private readonly array $osFilter,
    ) {
    }

    public static function fromDatabase(array $row): ClicksFilter
    {
        return new self(
            $row['markets'],
            $row['devices'],
            [],
            $row['os'],
        );
    }

    public function toArray(): array
    {
        return [
            'markets' => $this->marketFilter,
            'devices' => $this->deviceFilter,
            'userAgents' => $this->userAgentFilter,
            'os' => $this->osFilter,
        ];
    }

    public function getMarketFilter(): array
    {
        return $this->marketFilter;
    }

    public function getDeviceFilter(): array
    {
        return $this->deviceFilter;
    }

    public function getUserAgentFilter(): array
    {
        return $this->userAgentFilter;
    }

    public function getOsFilter(): array
    {
        return $this->osFilter;
    }
}
