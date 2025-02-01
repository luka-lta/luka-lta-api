<?php

namespace LukaLtaApi\Api\LinkCollection\Edit;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\LinkCollection\Edit\Service\EditLinkService;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class EditLinkAction extends ApiAction
{
    public function __construct(
        private readonly EditLinkService $service,
        private readonly RequestValidator $requestValidator,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = [
            'displayname' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'description' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
            'url' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'isActive' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
            'iconName' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
        ];

        $this->requestValidator->validate($request, $rules);

        $linkId = (int) $request->getAttribute('linkId');
        $displayname = $request->getParsedBody()['displayname'];
        $description = $request->getParsedBody()['description'] ?? null;
        $url = $request->getParsedBody()['url'];
        $isActive = $request->getParsedBody()['isActive'] ?? false;
        $iconName = $request->getParsedBody()['iconName'] ?? null;

        $editedLink = $this->service->edit($linkId, $displayname, $description, $url, $isActive, $iconName);

        return ApiResult::from(JsonResult::from('Link edited', [
            'link' => $editedLink->toArray()
        ]))->getResponse($response);
    }
}
