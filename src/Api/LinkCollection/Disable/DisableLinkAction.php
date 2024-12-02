<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\LinkCollection\Disable;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DisableLinkAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $validator,
        private
    )
    {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
    }
}
