<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Blog\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Blog\Service\BlogService;
use LukaLtaApi\Value\Blog\BlogId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UpdateBlogAction extends ApiAction
{
    public function __construct(
        private readonly BlogService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $blogId = BlogId::fromString($request->getAttribute('blogId'));

        return $this->service->updatePost($blogId, $request->getParsedBody())->getResponse($response);
    }
}
