<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository\Contracts;

interface SiteMetricRepositoryInterface
{
    public function getSiteMetricData(string $sql, array $params = []): array;
}
