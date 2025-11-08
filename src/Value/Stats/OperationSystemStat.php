<?php

namespace LukaLtaApi\Value\Stats;

class OperationSystemStat extends AbstractStat
{
    protected function getLabelKey(): string
    {
        return 'os';
    }
}
