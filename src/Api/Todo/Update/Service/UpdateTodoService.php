<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Todo\Update\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\TodoNotFoundException;
use LukaLtaApi\Repository\TodoRepository;
use LukaLtaApi\Value\TodoList\TodoDescription;
use LukaLtaApi\Value\TodoList\TodoDueDate;
use LukaLtaApi\Value\TodoList\TodoId;
use LukaLtaApi\Value\TodoList\TodoOwnerId;
use LukaLtaApi\Value\TodoList\TodoPriority;
use LukaLtaApi\Value\TodoList\TodoStatus;
use LukaLtaApi\Value\TodoList\TodoTitle;

class UpdateTodoService
{
    public function __construct(
        private readonly TodoRepository $todoRepository
    ) {
    }

    public function update(
        TodoOwnerId $ownerId,
        TodoId $todoId,
        string $title,
        ?string $description,
        ?string $status,
        ?string $priority,
        ?string $dueDate
    ): void {
        $todoObject = $this->todoRepository->load($todoId);

        if ($todoObject === null || $todoObject->getOwnerId()->asInt() !== $ownerId->asInt()) {
            throw new TodoNotFoundException(
                'Todo not found',
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        $todoObject->setTitle(TodoTitle::fromString($title));
        $todoObject->setDescription(TodoDescription::fromString($description));
        $todoObject->setStatus(TodoStatus::fromString($status));
        $todoObject->setPriority(TodoPriority::fromString($priority));
        $todoObject->setDueDate(TodoDueDate::fromString($dueDate));

        $this->todoRepository->update($todoObject);
    }
}
