<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Blog\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Blog\Service\BlogService;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Value\Blog\BlogId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PublishBlogAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
        private readonly BlogService      $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->requestValidator->validate($request, [
            'published' => ['required' => true, 'location' => 'body'],
        ]);

        $blogId  = BlogId::fromString($request->getAttribute('blogId'));
        $publish = filter_var(
            $request->getParsedBody()['published'],
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        ) ?? false;

        return $this->service->togglePublish($blogId, $publish)->getResponse($response);
    }
}
