<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\WebTracking\Site\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\SiteRepository;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\User\UserId;
use LukaLtaApi\Value\WebTracking\Config\SiteConfig;
use LukaLtaApi\Value\WebTracking\Site\Site;
use Psr\Http\Message\RequestInterface;

class SiteService
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
    ) {
    }

    public function createSite(RequestInterface $request): ApiResult
    {
        $body = $request->getParsedBody();
        var_dump($request->getAttribute('userId'));
        $requestedUser = UserId::fromString($request->getAttribute('userId'));
        $site = Site::create(
            $body['name'],
            $body['domain'],
            $requestedUser,
            SiteConfig::createDefaultConfig(),
        );

        $siteId = $this->siteRepository->createSiteId($site);

        return ApiResult::from(
            JsonResult::from('Site created with siteId ' . $siteId, [
                'siteId' => $siteId,
            ]),
            StatusCodeInterface::STATUS_CREATED,
        );
    }
}
