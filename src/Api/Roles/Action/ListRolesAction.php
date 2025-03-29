<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Roles\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Roles\Service\RolesService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ListRolesAction extends ApiAction
{
    public function __construct(
        private readonly RolesService $service
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getAvailableRoles()->getResponse($response);
    }
}
