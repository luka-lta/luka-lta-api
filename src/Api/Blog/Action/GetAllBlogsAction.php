<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Blog\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Blog\Service\BlogService;
use LukaLtaApi\Value\Blog\BlogFilter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetAllBlogsAction extends ApiAction
{
    public function __construct(
        private readonly BlogService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $filter          = BlogFilter::parseFromQuery($request->getQueryParams());
        $authHeader      = $request->getHeaderLine('Authorization');
        $isAuthenticated = !empty($authHeader);

        return $this->service->getAllPosts($filter, $isAuthenticated)->getResponse($response);
    }
}
