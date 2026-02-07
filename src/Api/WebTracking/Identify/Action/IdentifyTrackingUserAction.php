<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\WebTracking\Identify\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Api\WebTracking\Identify\Service\TrackingUserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class IdentifyTrackingUserAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $validator,
        private readonly TrackingUserService $trackingUserService,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = [
            'isNewIdentified' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'siteId' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'userId' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
        ];

        $this->validator->validate($request, $rules);

        return $this->trackingUserService->identifyUser($request)->getResponse($response);
    }
}
