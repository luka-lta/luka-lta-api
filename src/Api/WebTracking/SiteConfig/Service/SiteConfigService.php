<?php

namespace LukaLtaApi\Api\WebTracking\SiteConfig\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\SiteRepository;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\WebTracking\Config\SiteConfig;

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

    public function updateSiteConfig(int $siteId, array $parsedBody): ApiResult
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

        $siteConfig = SiteConfig::from(
            $parsedBody['webVitals'],
            $parsedBody['trackErrors'],
            $parsedBody['trackOutbound'],
            $parsedBody['trackUrlParams'],
            $parsedBody['trackInitial'],
            $parsedBody['trackSpaNavigation'],
        );

        $this->siteRepository->updateSite(
            $siteId,
            $parsedBody['name'],
            $parsedBody['domain'],
            $parsedBody['public'],
            $parsedBody['blockBots'],
            $parsedBody['excludedIps'],
            $parsedBody['excludedCountries'],
            $parsedBody['trackIp'],
            $siteConfig,
        );

        return ApiResult::from(
            JsonResult::from('Site Config updated for siteId ' . $siteId, [
                'config' => $siteConfig->toArray(),
            ])
        );
    }
}
