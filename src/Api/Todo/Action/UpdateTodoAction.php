<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Todo\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Api\Todo\Service\TaskService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UpdateTodoAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
        private readonly TaskService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = [
            'title' => ['required' => true, 'location' => 'body'],
            'description' => ['required' => false, 'location' => 'body'],
            'status' => ['required' => false, 'location' => 'body'],
            'priority' => ['required' => false, 'location' => 'body'],
            'dueDate' => ['required' => false, 'location' => 'body'],
        ];

        $this->requestValidator->validate($request, $rules);

        return $this->service->updateTask($request)->getResponse($response);
    }
}
