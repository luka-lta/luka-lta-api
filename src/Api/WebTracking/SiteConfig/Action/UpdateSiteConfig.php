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
            'name' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'domain' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'public' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'blockBots' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'webVitals' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'trackErrors' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'trackOutbound' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'trackUrlParams' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'trackInitial' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'trackSpaNavigation' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'trackIp' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'excludedCountries' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'excludedIps' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
        ];

        $this->requestValidator->validate($request, $rules);

        return $this->siteConfigService->updateSiteConfig(
            (int)$request->getAttribute('siteId'),
            $request->getParsedBody()
        )->getResponse($response);
    }
}
