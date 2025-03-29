<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\SelfUser\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\SelfUser\Service\SelfUserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetSelfUserAction extends ApiAction
{
    public function __construct(
        private readonly SelfUserService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getUser($request)->getResponse($response);
    }
}
