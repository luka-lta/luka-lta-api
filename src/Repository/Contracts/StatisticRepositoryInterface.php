<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository\Contracts;

use LukaLtaApi\Value\Stats\StatsCollection;

interface StatisticRepositoryInterface
{
    public function getStats(string $rowName, string $label, string $statClass): StatsCollection;
}
