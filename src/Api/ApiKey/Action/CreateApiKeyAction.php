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
        private readonly RequestValidator $requestValidator,
        private readonly ApiKeyService    $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->requestValidator->validate($request, [
            'label'         => ['required' => true, 'location' => 'body'],
            'origin'        => ['required' => true, 'location' => 'body'],
            'permissionIds' => ['required' => false, 'location' => 'body'],
            'expiresAt'     => ['required' => false, 'location' => 'body'],
        ]);

        $userId = (int) $request->getAttribute('userId');

        return $this->service->createApiKey($request->getParsedBody(), $userId)->getResponse($response);
    }
}
