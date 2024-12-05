<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\LinkCollection\Disable;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\LinkCollection\Disable\Service\DisableLinkService;
use LukaLtaApi\Value\LinkCollection\LinkId;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DisableLinkAction extends ApiAction
{
    public function __construct(
        private readonly DisableLinkService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $linkId = LinkId::fromString($request->getAttribute('linkId'));

        $this->service->disableLink($linkId);

        return ApiResult::from(JsonResult::from('Link disabled'))->getResponse($response);
    }
}
