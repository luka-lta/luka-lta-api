<?php

namespace LukaLtaApi\Api\LinkCollection\Create;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\LinkCollection\Create\Service\CreateLinkService;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreateLinkAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
        private readonly CreateLinkService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = [
            'displayname' => ['required' => true, 'location' => 'body'],
            'description' => ['required' => false, 'location' => 'body'],
            'url' => ['required' => true, 'location' => 'body'],
            'isActive' => ['required' => false, 'location' => 'body'],
            'iconName' => ['required' => false, 'location' => 'body'],
        ];

        $this->requestValidator->validate($request, $rules);

        $this->service->create(
            $request->getParsedBody()['displayname'],
            $request->getParsedBody()['description'] ?? null,
            $request->getParsedBody()['url'],
            $request->getParsedBody()['isActive'] ?? false,
            $request->getParsedBody()['iconName'] ?? null,
        );

        return ApiResult::from(JsonResult::from('Link created'))->getResponse($response);
    }
}
