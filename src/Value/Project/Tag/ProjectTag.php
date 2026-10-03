<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project\Tag;

use DateTimeImmutable;

class ProjectTag
{
    private function __construct(
        private readonly ?ProjectTagId     $tagId,
        private readonly ProjectTagName    $name,
        private readonly ProjectTagSlug    $slug,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(string $name): self
    {
        return new self(
            null,
            ProjectTagName::fromString($name),
            ProjectTagSlug::fromName($name),
            new DateTimeImmutable(),
        );
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            ProjectTagId::fromInt((int) $row['tag_id']),
            ProjectTagName::fromString($row['name']),
            ProjectTagSlug::fromString($row['slug']),
            new DateTimeImmutable($row['created_at']),
        );
    }

    public function getTagId(): ?ProjectTagId
    {
        return $this->tagId;
    }

    public function getName(): ProjectTagName
    {
        return $this->name;
    }

    public function getSlug(): ProjectTagSlug
    {
        return $this->slug;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'tagId' => $this->tagId?->asInt(),
            'name'  => (string) $this->name,
            'slug'  => (string) $this->slug,
        ];
    }
}
