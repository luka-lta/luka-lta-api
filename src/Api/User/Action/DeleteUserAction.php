<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\User\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\User\Service\UserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DeleteUserAction extends ApiAction
{
    public function __construct(
        private readonly UserService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->deleteUser(
            $request->getAttribute('userId'),
            $response
        );
    }
}
