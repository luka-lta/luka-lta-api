<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Blog\Tag;

use DateTimeImmutable;

class Tag
{
    private function __construct(
        private readonly ?TagId           $tagId,
        private readonly TagName          $name,
        private readonly TagSlug          $slug,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(string $name): self
    {
        return new self(
            null,
            TagName::fromString($name),
            TagSlug::fromName($name),
            new DateTimeImmutable(),
        );
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            TagId::fromInt((int) $row['tag_id']),
            TagName::fromString($row['name']),
            TagSlug::fromString($row['slug']),
            new DateTimeImmutable($row['created_at']),
        );
    }

    public function getTagId(): ?TagId
    {
        return $this->tagId;
    }

    public function getName(): TagName
    {
        return $this->name;
    }

    public function getSlug(): TagSlug
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
            'tagId'     => $this->tagId?->asInt(),
            'name'      => (string) $this->name,
            'slug'      => (string) $this->slug,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
