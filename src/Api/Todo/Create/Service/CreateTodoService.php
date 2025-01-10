<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Todo\Create\Service;

use LukaLtaApi\Repository\TodoRepository;
use LukaLtaApi\Value\TodoList\TodoObject;

class CreateTodoService
{
    public function __construct(
        private readonly TodoRepository $todoRepository,
    ) {
    }

    public function create(
        int $ownerId,
        string $title,
        ?string $description,
        string $status,
        string $priority,
        ?string $dueDate,
    ): void {
        $todo = TodoObject::create(
            $ownerId,
            $title,
            $description,
            $status,
            $priority,
            $dueDate,
        );

        $this->todoRepository->create($todo);
    }
}
