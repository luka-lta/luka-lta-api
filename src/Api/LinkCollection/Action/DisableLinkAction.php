<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\LinkCollection\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\LinkCollection\Service\LinkCollectionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DisableLinkAction extends ApiAction
{
    public function __construct(
        private readonly LinkCollectionService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->disableLink($request->getAttributes())->getResponse($response);
    }
}
