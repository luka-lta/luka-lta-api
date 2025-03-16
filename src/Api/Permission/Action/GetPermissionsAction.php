<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Permission\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Permission\Service\PermissionsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetPermissionsAction extends ApiAction
{
    public function __construct(
        private readonly PermissionsService $service
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getPermissions()->getResponse($response);
    }
}
