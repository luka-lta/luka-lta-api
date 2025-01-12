<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Todo\GetAll;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Todo\GetAll\Service\GetAllTodoService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\TodoList\TodoOwnerId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetAllTodoAction extends ApiAction
{
    public function __construct(
        private readonly GetAllTodoService $service
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $ownerId = TodoOwnerId::fromString($request->getAttribute('userId'));
        $todos = $this->service->getAll($ownerId);

        $message = 'Todos fetched successfully';

        if ($todos === null) {
            $message = 'No todos found';
        }

        return ApiResult::from(JsonResult::from(
            $message,
            $todos === null ? null : [
                'todos' => $todos
            ]
        ))->getResponse($response);
    }
}
