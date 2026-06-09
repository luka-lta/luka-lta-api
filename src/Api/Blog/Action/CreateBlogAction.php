<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Blog\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Blog\Service\BlogService;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreateBlogAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
        private readonly BlogService      $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->requestValidator->validate($request, [
            'title'   => ['required' => true,  'location' => 'body'],
            'content' => ['required' => true,  'location' => 'body'],
            'excerpt' => ['required' => false, 'location' => 'body'],
            'tag_ids' => ['required' => false, 'location' => 'body'],
        ]);

        $userId = (int) $request->getAttribute('userId');

        return $this->service->createPost($request->getParsedBody(), $userId)->getResponse($response);
    }
}
