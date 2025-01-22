<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Permission\GetPermissions;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Permission\GetPermissions\Service\GetPermissionsService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetPermissionsAction extends ApiAction
{
    public function __construct(
        private readonly GetPermissionsService $getPermissionsService
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $permissions = $this->getPermissionsService->getPermissions();

        $message = 'Permissions fetched successfully';

        if ($permissions === null) {
            $message = 'No permissions found';
        }

        return ApiResult::from(JsonResult::from(
            $message,
            $permissions === null ? null : [
                'permissions' => $permissions
            ]
        ))->getResponse($response);
    }
}
