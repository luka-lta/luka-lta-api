<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\TodoList;

class Tasks implements \IteratorAggregate, \JsonSerializable, \Countable
{
    private readonly array $tasks;

    private function __construct(TodoObject ...$tasks)
    {
        $this->tasks = $tasks;
    }

    public static function from(array ...$tasks): self
    {
        $tasksList = [];

        foreach ($tasks as $task) {
            if ($task['todo_id'] === null) {
                $task[] = [];
                continue;
            }

            $tasksList[] = TodoObject::fromDatabase($task);
        }

        return new self(...$tasksList);
    }

    public static function fromObjects(TodoObject ...$tasks): self
    {
        return new self(...$tasks);
    }

    public function getIterator(): \Generator
    {
        yield from $this->tasks;
    }

    public function toArray(): array
    {
        return array_map(static fn($task) => $task->toArray(), $this->tasks);
    }

    public function jsonSerialize(): array
    {
        return $this->tasks;
    }

    public function count(): int
    {
        return count($this->tasks);
    }
}
