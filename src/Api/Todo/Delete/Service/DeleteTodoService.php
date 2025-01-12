<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Todo\Delete\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\TodoNotFoundException;
use LukaLtaApi\Repository\TodoRepository;
use LukaLtaApi\Value\TodoList\TodoId;
use LukaLtaApi\Value\TodoList\TodoOwnerId;

class DeleteTodoService
{
    public function __construct(
        private readonly TodoRepository $todoRepository
    ) {
    }

    public function delete(TodoId $todoId, TodoOwnerId $ownerId): void
    {
        $findTodo = $this->todoRepository->load($todoId);

        if ($findTodo === null || $findTodo->getOwnerId()->asInt() !== $ownerId->asInt()) {
            throw new TodoNotFoundException(
                'Todo not found',
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        $this->todoRepository->delete($todoId);
    }
}
