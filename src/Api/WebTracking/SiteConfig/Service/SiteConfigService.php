<?php

namespace LukaLtaApi\Api\WebTracking\SiteConfig\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\SiteRepository;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;

class SiteConfigService
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
    ) {
    }

    public function getSiteConfig(int $siteId): ApiResult
    {
        $site = $this->siteRepository->getSite($siteId);

        if (!$site) {
            return ApiResult::from(
                JsonResult::from(
                    'No SiteConfig found for siteId ' . $siteId,
                ),
                StatusCodeInterface::STATUS_NOT_FOUND,
            );
        }

        return ApiResult::from(
            JsonResult::from('Site Config found for siteId ' . $siteId, [
                'config' => $site->getSiteConfig()->toArray(),
            ]),
        );
    }
}
