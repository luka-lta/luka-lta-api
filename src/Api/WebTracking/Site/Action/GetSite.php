<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\WebTracking\Site\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\WebTracking\SiteConfig\Service\SiteConfigService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetSite extends ApiAction
{
    public function __construct(
        private readonly SiteConfigService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getSite((int)$request->getAttribute('siteId'))->getResponse($response);
    }
}
