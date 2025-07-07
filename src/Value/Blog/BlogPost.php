<?php

namespace LukaLtaApi\Value\Blog;

use DateTimeImmutable;
use LukaLtaApi\Value\User\UserId;
use Ramsey\Uuid\Uuid;

class BlogPost
{
    private function __construct(
        private readonly string            $blogId,
        private readonly UserId            $userId,
        private string                     $title,
        private BlogContent                $content,
        private bool                       $isPublished,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable         $updatedAt,
    ) {
    }

    public static function create(
        UserId             $userId,
        string             $title,
        string             $content,
        bool               $isPublished,
        DateTimeImmutable  $createdAt,
        ?DateTimeImmutable $updatedAt = null
    ): self {
        return new self(
            Uuid::uuid4()->toString(),
            $userId,
            $title,
            BlogContent::fromRaw($content),
            $isPublished,
            $createdAt,
            $updatedAt
        );
    }

    public static function from(
        string             $blogId,
        UserId             $userId,
        string             $title,
        string             $content,
        bool               $isPublished,
        DateTimeImmutable  $createdAt,
        ?DateTimeImmutable $updatedAt = null
    ): self {
        return new self(
            $blogId,
            $userId,
            $title,
            BlogContent::fromRaw($content),
            $isPublished,
            $createdAt,
            $updatedAt
        );
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            $row['blog_id'],
            UserId::fromString($row['user_id']),
            $row['title'],
            BlogContent::fromRaw($row['content']),
            $row['is_published'],
            new DateTimeImmutable($row['created_at']),
            isset($row['updated_at']) ? new DateTimeImmutable($row['updated_at']) : null
        );
    }

    public function toArray(): array
    {
        return [
            'blogId' => $this->blogId,
            'userId' => $this->userId->asInt(),
            'title' => $this->title,
            'content' => $this->content->getContent(),
            'isPublished' => $this->isPublished,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    public function getBlogId(): string
    {
        return $this->blogId;
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): BlogContent
    {
        return $this->content;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setContent(BlogContent $content): void
    {
        $this->content = $content;
    }

    public function setIsPublished(bool $isPublished): void
    {
        $this->isPublished = $isPublished;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
