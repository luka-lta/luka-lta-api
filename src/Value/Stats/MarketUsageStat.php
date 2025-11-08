<?php

namespace LukaLtaApi\Value\Stats;

class MarketUsageStat extends AbstractStat
{
    protected function getLabelKey(): string
    {
        return 'market';
    }
}
