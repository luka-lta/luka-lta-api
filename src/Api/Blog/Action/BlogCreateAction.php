<?php

namespace LukaLtaApi\Api\Blog\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Blog\Service\BlogService;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class BlogCreateAction extends ApiAction
{
    public function __construct(
        private readonly BlogService $service,
        private readonly RequestValidator $validator,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = [
            'title' => ['required' => true, 'location' => 'body'],
            'content' => ['required' => true, 'location' => 'body'],
        ];

        $this->validator->validate($request, $rules);

        return $this->service->createBlog($request)->getResponse($response);
    }
}
