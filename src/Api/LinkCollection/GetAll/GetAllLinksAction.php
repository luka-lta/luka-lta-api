<?php

namespace LukaLtaApi\Api\LinkCollection\GetAll;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetAllLinksAction extends ApiAction
{
    public function __construct(
        private readonly GetAllLinksService $service
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $links = $this->service->getAllLinks();

        $message = 'Links fetched successfully';

        if ($links === null) {
            $message = 'No links found';
        }

        return ApiResult::from(JsonResult::from(
            $message,
            $links === null ? null : [
                'links' => $links
            ]
        ))->getResponse($response);
    }
}
