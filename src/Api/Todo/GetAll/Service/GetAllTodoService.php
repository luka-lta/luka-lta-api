<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Todo\GetAll\Service;

use LukaLtaApi\Repository\TodoRepository;
use LukaLtaApi\Value\TodoList\TodoObject;
use LukaLtaApi\Value\TodoList\TodoOwnerId;

class GetAllTodoService
{
    public function __construct(
        private readonly TodoRepository $todoRepository
    ) {
    }

    public function getAll(TodoOwnerId $ownerId): ?array
    {
        $todos = $this->todoRepository->loadAllByOwnerId($ownerId);

        if (!$todos) {
            return null;
        }

        return array_map(static fn(TodoObject $todo) => $todo->toArray(), $todos);
    }
}
