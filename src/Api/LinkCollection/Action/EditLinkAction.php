<?php

namespace LukaLtaApi\Api\LinkCollection\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\LinkCollection\Service\LinkCollectionService;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class EditLinkAction extends ApiAction
{
    public function __construct(
        private readonly LinkCollectionService $service,
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
        return $this->service->editLink($request)->getResponse($response);
    }
}
