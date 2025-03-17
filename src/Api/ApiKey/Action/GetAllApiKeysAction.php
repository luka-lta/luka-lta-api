<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\ApiKey\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\ApiKey\Service\ApiKeyService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetAllApiKeysAction extends ApiAction
{
    public function __construct(
        private readonly ApiKeyService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getAllKeys()->getResponse($response);
    }
}
