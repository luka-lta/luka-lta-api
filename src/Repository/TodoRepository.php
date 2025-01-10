<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\TodoList\TodoObject;
use PDO;
use PDOException;

class TodoRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(TodoObject $todo): void
    {
        $sql = <<<SQL
            INSERT INTO todo_list (owner_id, title, description, status, priority, due_date, created_at)
            VALUES (:ownerId, :title, :description, :status, :priority, :due_date, :created_at)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'ownerId' => $todo->getOwnerId()->asInt(),
                'title' => $todo->getTitle()->toString(),
                'description' => $todo->getDescription()?->toString(),
                'status' => $todo->getStatus()->toString(),
                'priority' => $todo->getPriority()->toString(),
                'due_date' => $todo->getDueDate()?->toDateObject()->format('Y-m-d H:i:s'),
                'created_at' => $todo->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to create Todo',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }

    public function update(TodoObject $todo): void
    {
        $sql = <<<SQL
            UPDATE todo_list
            SET 
                title = :title, 
                description = :description, 
                status = :status, 
                priority = :priority, 
                due_date = :due_date
            WHERE todo_id = :todo_id
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'todo_id' => $todo->getTodoId()?->asInt(),
                'title' => $todo->getTitle()->toString(),
                'description' => $todo->getDescription()?->toString(),
                'status' => $todo->getStatus()->toString(),
                'priority' => $todo->getPriority()->toString(),
                'due_date' => $todo->getDueDate()?->toDateObject()->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to update Todo',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }

    public function delete(int $todoId): void
    {
        $sql = <<<SQL
            DELETE FROM todo_list
            WHERE todo_id = :todo_id
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['todo_id' => $todoId]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to delete Todo',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }

    public function load(int $todoId): ?TodoObject
    {
        $sql = <<<SQL
            SELECT todo_id, owner_id, title, description, status, priority, due_date, created_at, updated_at
            FROM todo_list
            WHERE todo_id = :todo_id
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['todo_id' => $todoId]);
            $row = $stmt->fetch();

            if ($row === false) {
                return null;
            }

            return TodoObject::fromDatabase($row);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to load Todo',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }

    public function loadAll(): ?array
    {
        $sql = <<<SQL
            SELECT todo_id, owner_id, title, description, status, priority, due_date, created_at, updated_at
            FROM todo_list
        SQL;

        try {
            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll();

            if (empty($rows)) {
                return null;
            }

            return array_map(static fn($row) => TodoObject::fromDatabase($row), $rows);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to load Todo list',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }
}
