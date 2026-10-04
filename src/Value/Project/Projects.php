<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project;

use Countable;
use Generator;
use IteratorAggregate;

class Projects implements IteratorAggregate, Countable
{
    private readonly array $projects;

    private function __construct(Project ...$projects)
    {
        $this->projects = $projects;
    }

    public static function from(Project ...$projects): self
    {
        return new self(...$projects);
    }

    public static function empty(): self
    {
        return new self();
    }

    public function getIterator(): Generator
    {
        yield from $this->projects;
    }

    public function count(): int
    {
        return count($this->projects);
    }

    public function toArray(string $assetBaseUrl): array
    {
        return array_map(static fn(Project $project) => $project->toArray($assetBaseUrl), $this->projects);
    }
}
