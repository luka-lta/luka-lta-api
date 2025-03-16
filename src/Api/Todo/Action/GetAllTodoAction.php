<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Todo\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Todo\Service\TaskService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetAllTodoAction extends ApiAction
{
    public function __construct(
        private readonly TaskService $service
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getAllTasks($request)->getResponse($response);
    }
}
