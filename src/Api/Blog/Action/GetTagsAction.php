<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Blog\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Blog\Service\BlogTagService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetTagsAction extends ApiAction
{
    public function __construct(
        private readonly BlogTagService $service,
    ) {
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getTags()->getResponse($response);
    }
}
