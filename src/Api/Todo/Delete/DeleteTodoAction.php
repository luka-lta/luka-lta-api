<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Todo\Delete;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Todo\Delete\Service\DeleteTodoService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\TodoList\TodoId;
use LukaLtaApi\Value\TodoList\TodoOwnerId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DeleteTodoAction extends ApiAction
{
    public function __construct(
        private readonly DeleteTodoService $service
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $ownerId = TodoOwnerId::fromString($request->getAttribute('userId'));
        $todoId = TodoId::fromString($request->getAttribute('todoId'));

        $this->service->delete($todoId, $ownerId);

        return ApiResult::from(JsonResult::from('Todo deleted'))->getResponse($response);
    }
}
