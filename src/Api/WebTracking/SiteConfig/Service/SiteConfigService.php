<?php

namespace LukaLtaApi\Api\WebTracking\SiteConfig\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\SiteRepository;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\WebTracking\Config\SiteConfig;
use LukaLtaApi\Value\WebTracking\Site\Site;
use Psr\Http\Message\RequestInterface;

class SiteConfigService
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
    ) {
    }

    public function getSite(int $siteId): ApiResult
    {
        $site = $this->siteRepository->getSite($siteId);

        if (!$site) {
            return ApiResult::from(
                JsonResult::from(
                    'No Site found for siteId ' . $siteId,
                ),
                StatusCodeInterface::STATUS_NOT_FOUND,
            );
        }

        return ApiResult::from(
            JsonResult::from('Site found for siteId ' . $siteId, [
                'site' => $site->toArray()
            ]),
        );
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
        // TODO: Check if domain changed and already exists
        $site = $this->siteRepository->getSite($siteId);

        if (!$site) {
            return ApiResult::from(
                JsonResult::from(
                    'No SiteConfig found for siteId ' . $siteId,
                ),
                StatusCodeInterface::STATUS_NOT_FOUND,
            );
        }

        $updateData = [];

        $map = [
            'name' => 'name',
            'domain' => 'domain',
            'public' => 'public',
            'blockBots' => 'block_bots',
            'trackIp' => 'track_ip',
        ];

        foreach ($map as $requestKey => $dbColumn) {
            if (array_key_exists($requestKey, $parsedBody)) {
                $updateData[$dbColumn] = $parsedBody[$requestKey];
            }
        }

        if (isset($parsedBody['excludedIps'])) {
            $updateData['excluded_ips'] = json_encode($parsedBody['excludedIps'], JSON_THROW_ON_ERROR);
        }

        if (isset($parsedBody['excludedCountries'])) {
            $updateData['excluded_countries'] = json_encode($parsedBody['excludedCountries'], JSON_THROW_ON_ERROR);
        }

        // SiteConfig
        $siteConfigMap = [
            'webVitals' => 'web_vitals',
            'trackErrors' => 'track_errors',
            'trackOutbound' => 'track_outbound',
            'trackUrlParams' => 'track_url_params',
            'trackInitial' => 'track_initial',
            'trackSpaNavigation' => 'track_spa_navigation',
            'trackButtonClicks' => 'track_button_clicks',
            'trackCopy' => 'track_copy',
            'trackFormInteractions' => 'track_form_interactions',
        ];

        foreach ($siteConfigMap as $requestKey => $dbColumn) {
            if (array_key_exists($requestKey, $parsedBody)) {
                $updateData[$dbColumn] = (bool) $parsedBody[$requestKey];
            }
        }

        $this->siteRepository->updateSite(
            $siteId,
            $updateData
        );

        return ApiResult::from(
            JsonResult::from('Site Config updated for siteId ' . $siteId)
        );
    }
}
