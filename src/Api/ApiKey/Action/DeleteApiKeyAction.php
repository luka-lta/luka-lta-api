<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\ApiKey\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\ApiKey\Service\ApiKeyService;
use LukaLtaApi\Value\ApiKey\ApiKeyId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DeleteApiKeyAction extends ApiAction
{
    public function __construct(
        private readonly ApiKeyService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $keyId = ApiKeyId::fromString($request->getAttribute('keyId'));

        return $this->service->deleteApiKey($keyId)->getResponse($response);
    }
}
