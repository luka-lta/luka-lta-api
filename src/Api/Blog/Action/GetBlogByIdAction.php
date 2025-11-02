<?php

namespace LukaLtaApi\Api\Blog\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Blog\Service\BlogService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetBlogByIdAction extends ApiAction
{
    public function __construct(
        private readonly BlogService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getBlogById($request)->getResponse($response);
    }
}
