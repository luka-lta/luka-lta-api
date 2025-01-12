<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Todo\Update;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Api\Todo\Update\Service\UpdateTodoService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\TodoList\TodoId;
use LukaLtaApi\Value\TodoList\TodoOwnerId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UpdateTodoAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
        private readonly UpdateTodoService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $ownerId = TodoOwnerId::fromString($request->getAttribute('userId'));
        $todoId = TodoId::fromString($request->getAttribute('todoId'));

        $rules = [
            'title' => ['required' => true, 'location' => 'body'],
            'description' => ['required' => false, 'location' => 'body'],
            'status' => ['required' => false, 'location' => 'body'],
            'priority' => ['required' => false, 'location' => 'body'],
            'dueDate' => ['required' => false, 'location' => 'body'],
        ];

        $this->requestValidator->validate($request, $rules);

        $this->service->update(
            $ownerId,
            $todoId,
            $body['title'],
            $body['description'] ?? null,
            $body['status'] ?? null,
            $body['priority'] ?? null,
            $body['dueDate'] ?? null,
        );

        return ApiResult::from(JsonResult::from('Todo updated'))->getResponse($response);
    }
}
