<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Todo\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\TodoRepository;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\TodoList\TodoDescription;
use LukaLtaApi\Value\TodoList\TodoDueDate;
use LukaLtaApi\Value\TodoList\TodoId;
use LukaLtaApi\Value\TodoList\TodoObject;
use LukaLtaApi\Value\TodoList\TodoOwnerId;
use LukaLtaApi\Value\TodoList\TodoPriority;
use LukaLtaApi\Value\TodoList\TodoStatus;
use LukaLtaApi\Value\TodoList\TodoTitle;
use Psr\Http\Message\ServerRequestInterface;

class TaskService
{
    public function __construct(
        private readonly TodoRepository $todoRepository,
    ) {
    }

    public function createTask(ServerRequestInterface $request): ApiResult
    {
        $body = $request->getParsedBody();
        $ownerId = (int)$request->getAttribute('userId');

        $taskObject = TodoObject::create(
            $ownerId,
            $body['title'],
            $body['description'] ?? null,
            $body['status'] ?? null,
            $body['priority'] ?? null,
            $body['dueDate'] ?? null,
        );

        $this->todoRepository->create($taskObject);

        return ApiResult::from(JsonResult::from('Task created', [
            'task' => $taskObject->toArray(),
        ]));
    }

    public function deleteTask(ServerRequestInterface $request): ApiResult
    {
        $ownerId = TodoOwnerId::fromString($request->getAttribute('userId'));
        $todoId = TodoId::fromString($request->getAttribute('todoId'));

        $findTodo = $this->todoRepository->load($todoId);

        if ($findTodo === null || $findTodo->getOwnerId()->asInt() !== $ownerId->asInt()) {
            return ApiResult::from(
                JsonResult::from('Task not found'),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        return ApiResult::from(JsonResult::from('Task deleted'));
    }

    public function updateTask(ServerRequestInterface $request): ApiResult
    {
        $body = $request->getParsedBody();
        $ownerId = TodoOwnerId::fromString($request->getAttribute('userId'));
        $todoId = TodoId::fromString($request->getAttribute('todoId'));

        $taskObject = $this->todoRepository->load($todoId);

        if ($taskObject === null || $taskObject->getOwnerId()->asInt() !== $ownerId->asInt()) {
            return ApiResult::from(
                JsonResult::from('Task not found'),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        $taskObject->setTitle(TodoTitle::fromString($body['title']));
        $taskObject->setDescription(TodoDescription::fromString($body['description']));
        $taskObject->setStatus(TodoStatus::fromString($body['status']));
        $taskObject->setPriority(TodoPriority::fromString($body['priority']));
        $taskObject->setDueDate(TodoDueDate::fromString($body['dueDate']));

        $this->todoRepository->update($taskObject);

        return ApiResult::from(JsonResult::from('Task updated'));
    }

    public function getAllTasks(ServerRequestInterface $request): ApiResult
    {
        $ownerId = TodoOwnerId::fromString($request->getAttribute('userId'));
        $tasks = $this->todoRepository->loadAllByOwnerId($ownerId);

        if ($tasks->count() === 0) {
            return ApiResult::from(
                JsonResult::from('No tasks found'),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        return ApiResult::from(JsonResult::from('Tasks fetched successfully', [
            'tasks' => $tasks->toArray(),
        ]));
    }
}
