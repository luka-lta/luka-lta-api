<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Blog\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Blog\Service\BlogTagService;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreateTagAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
        private readonly BlogTagService   $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->requestValidator->validate($request, [
            'name' => ['required' => true, 'location' => 'body'],
        ]);

        $name = $request->getParsedBody()['name'];

        return $this->service->createTag($name)->getResponse($response);
    }
}
