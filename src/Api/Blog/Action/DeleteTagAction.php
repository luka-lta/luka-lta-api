<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Blog\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Blog\Service\BlogTagService;
use LukaLtaApi\Value\Blog\Tag\TagId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DeleteTagAction extends ApiAction
{
    public function __construct(
        private readonly BlogTagService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tagId = TagId::fromString($request->getAttribute('tagId'));

        return $this->service->deleteTag($tagId)->getResponse($response);
    }
}
