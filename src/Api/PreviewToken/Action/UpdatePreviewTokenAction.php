<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\PreviewToken\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\PreviewToken\Service\PreviewTokenService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UpdatePreviewTokenAction extends ApiAction
{
    public function __construct(
        private readonly PreviewTokenService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->editToken($request, $response);
    }
}
