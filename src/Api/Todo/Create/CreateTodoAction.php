<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Todo\Create;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Api\Todo\Create\Service\CreateTodoService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreateTodoAction extends ApiAction
{
    public function __construct(
        private readonly CreateTodoService $createTodoService,
        private readonly RequestValidator $requestValidator
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $ownerId = (int)$request->getAttribute('userId');

        $rules = [
            'title' => ['required' => true, 'location' => 'body'],
            'description' => ['required' => false, 'location' => 'body'],
            'status' => ['required' => true, 'location' => 'body'],
            'priority' => ['required' => true, 'location' => 'body'],
            'dueDate' => ['required' => false, 'location' => 'body'],
        ];

        $this->requestValidator->validate($request, $rules);

        $this->createTodoService->create(
            $ownerId,
            $body['title'],
            $body['description'],
            $body['status'],
            $body['priority'],
            $body['dueDate'],
        );

        return ApiResult::from(JsonResult::from('Todo created'))->getResponse($response);
    }
}
