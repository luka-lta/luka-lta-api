<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository\Contracts;

use LukaLtaApi\Value\WebTracking\Site\Site;

interface SiteRepositoryInterface
{
    public function getSite(int $siteId): ?Site;

    public function createSiteId(Site $site): int;

    public function updateSite(int $siteId, array $updateData): void;
}
