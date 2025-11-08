<?php

namespace LukaLtaApi\Value\Stats;

class DeviceStat extends AbstractStat
{
    protected function getLabelKey(): string
    {
        return 'device';
    }
}
