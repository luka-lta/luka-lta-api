<?php

namespace LukaLtaApi\Api\LinkCollection\Create;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreateLinkAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
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

        // Service aufrufen

        return $this->execute($request, $response);
    }
}
