<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\WebTracking\SiteConfig\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Api\WebTracking\SiteConfig\Service\SiteConfigService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UpdateSiteConfig extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
        private readonly SiteConfigService $siteConfigService
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = [
            'name' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
            'domain' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
            'public' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
            'blockBots' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
            'webVitals' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
            'trackErrors' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
            'trackOutbound' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
            'trackUrlParams' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
            'trackInitial' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
            'trackSpaNavigation' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
            'trackIp' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
            'excludedCountries' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
            'excludedIps' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
        ];

        $this->requestValidator->validate($request, $rules);

        return $this->siteConfigService->updateSiteConfig(
            (int)$request->getAttribute('siteId'),
            $request->getParsedBody()
        )->getResponse($response);
    }
}
