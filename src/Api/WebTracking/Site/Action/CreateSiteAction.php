<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\WebTracking\Site\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Api\WebTracking\Site\Service\SiteService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreateSiteAction extends ApiAction
{
    public function __construct(
        private readonly SiteService $service,
        private readonly RequestValidator $validator,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = [
            'name' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'domain' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
        ];

        $this->validator->validate($request, $rules);

        return $this->service->createSite($request)->getResponse($response);
    }
}
