<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\User\GetAll;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\User\GetAll\Service\GetAllUsersService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetAllUsersAction extends ApiAction
{
    public function __construct(
        private readonly GetAllUsersService $service
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $users = $this->service->getAll();

        $message = 'Users fetched successfully';

        if ($users === null) {
            $message = 'No users found';
        }

        return ApiResult::from(JsonResult::from(
            $message,
            $users === null ? null : [
                'users' => $users
            ]
        ))->getResponse($response);
    }
}
