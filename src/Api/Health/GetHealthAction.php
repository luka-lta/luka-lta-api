<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Health;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetHealthAction extends ApiAction
{
    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ApiResult::from(
            JsonResult::from(
                'Health check passed'
            )
        )->getResponse($response);
    }
}
