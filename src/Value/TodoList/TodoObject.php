<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\TodoList;

use DateTimeImmutable;

class TodoObject
{
    private function __construct(
        private readonly ?TodoId $todoId,
        private readonly TodoOwnerId $ownerId,
        private TodoTitle $title,
        private TodoDescription $description,
        private TodoStatus $status,
        private TodoPriority $priority,
        private TodoDueDate $dueDate,
        private readonly DateTimeImmutable $createdAt,
        private readonly ?DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        int $ownerId,
        string $title,
        ?string $description,
        ?string $status,
        ?string $priority,
        ?string $dueDate,
    ): self {
        return new self(
            null,
            TodoOwnerId::fromInt($ownerId),
            TodoTitle::fromString($title),
            TodoDescription::fromString($description),
            TodoStatus::fromString($status),
            TodoPriority::fromString($priority),
            TodoDueDate::fromString($dueDate),
            new DateTimeImmutable(),
            null
        );
    }

    public static function fromDatabase(array $rows): self
    {
        return new self(
            TodoId::fromInt($rows['todo_id']),
            TodoOwnerId::fromInt($rows['owner_id']),
            TodoTitle::fromString($rows['title']),
            TodoDescription::fromString($rows['description']),
            TodoStatus::fromString($rows['status']),
            TodoPriority::fromString($rows['priority']),
            TodoDueDate::fromString($rows['due_date']),
            new DateTimeImmutable($rows['created_at']),
            new DateTimeImmutable($rows['updated_at'])
        );
    }

    public function toArray(): array
    {
        return [
            'todoId' => $this->todoId?->asInt(),
            'ownerId' => $this->ownerId->asInt(),
            'title' => $this->title->toString(),
            'description' => $this->description->toString(),
            'status' => $this->status->toString(),
            'priority' => $this->priority->toString(),
            'dueDate' => $this->dueDate->toString(),
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    public function getTodoId(): ?TodoId
    {
        return $this->todoId;
    }

    public function getOwnerId(): TodoOwnerId
    {
        return $this->ownerId;
    }

    public function getTitle(): TodoTitle
    {
        return $this->title;
    }

    public function getDescription(): TodoDescription
    {
        return $this->description;
    }

    public function getStatus(): TodoStatus
    {
        return $this->status;
    }

    public function getPriority(): TodoPriority
    {
        return $this->priority;
    }

    public function getDueDate(): TodoDueDate
    {
        return $this->dueDate;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setTitle(TodoTitle $title): void
    {
        $this->title = $title;
    }

    public function setDescription(TodoDescription $description): void
    {
        $this->description = $description;
    }

    public function setStatus(TodoStatus $status): void
    {
        $this->status = $status;
    }

    public function setPriority(TodoPriority $priority): void
    {
        $this->priority = $priority;
    }

    public function setDueDate(TodoDueDate $dueDate): void
    {
        $this->dueDate = $dueDate;
    }
}
