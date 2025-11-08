<?php

namespace LukaLtaApi\Value\Stats;

class BrowserUsageStat extends AbstractStat
{
    protected function getLabelKey(): string
    {
        return 'browser';
    }
}
