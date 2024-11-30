<?php

namespace LukaLtaApi\Api\Click\GetAll;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Click\GetAll\Service\GetAllClicksService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetAllClicksAction extends ApiAction
{
    public function __construct(
        private readonly GetAllClicksService $service
    )
    {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $clicks = $this->service->getAllClicks();

        $message = 'Clicks fetched successfully';

        if ($clicks === null) {
            $message = 'No clicks found';
        }

        return ApiResult::from(JsonResult::from(
            $message,
            $clicks === null ? null : [
                'clicks' => $clicks
            ]
        ))->getResponse($response);
    }
}
