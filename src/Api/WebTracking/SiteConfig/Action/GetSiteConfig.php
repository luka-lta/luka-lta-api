<?php

namespace LukaLtaApi\Api\WebTracking\SiteConfig\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\WebTracking\SiteConfig\Service\SiteConfigService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetSiteConfig extends ApiAction
{
    public function __construct(
        private readonly SiteConfigService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getSiteConfig($request->getAttribute('siteId'))->getResponse($response);
    }
}
