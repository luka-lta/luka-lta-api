<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\ApiKey\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\ApiKey\Service\ApiKeyService;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreateApiKeyAction extends ApiAction
{
    public function __construct(
        private readonly ApiKeyService $service,
        private readonly RequestValidator    $validator,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = [
            'origin' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'expiresAt' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
            'permissions' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
        ];

        $this->validator->validate($request, $rules);

        return $this->service->create($request)->getResponse($response);
    }
}
