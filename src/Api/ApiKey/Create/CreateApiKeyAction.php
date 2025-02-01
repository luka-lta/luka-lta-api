<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\ApiKey\Create;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\ApiKey\Create\Service\CreateApiKeyService;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreateApiKeyAction extends ApiAction
{
    public function __construct(
        private readonly CreateApiKeyService $service,
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

        $body = $request->getParsedBody();
        $createdBy = (int)$request->getAttribute('userId');

        $apiKey = $this->service->create(
            $body['origin'],
            $createdBy,
            $body['expiresAt'] ?? null,
            $body['permissions'] ?? [],
        );

        return ApiResult::from(
            JsonResult::from('Api key created successfully', [
                'apiKey' => (string)$apiKey->getApiKey(),
            ]),
            StatusCodeInterface::STATUS_CREATED
        )->getResponse($response);
    }
}
