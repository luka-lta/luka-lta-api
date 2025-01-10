<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\ApiKey\GetAll;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\ApiKey\GetAll\Service\GetAllApiKeysService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetAllApiKeysAction extends ApiAction
{
    public function __construct(
        private readonly GetAllApiKeysService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $apiKeys = $this->service->loadAll();

        $message = 'API keys fetched successfully';

        if ($apiKeys === null) {
            $message = 'No API keys found';
        }

        return ApiResult::from(JsonResult::from(
            $message,
            $apiKeys === null ? null : [
                'apiKeys' => $apiKeys
            ]
        ))->getResponse($response);
    }
}
