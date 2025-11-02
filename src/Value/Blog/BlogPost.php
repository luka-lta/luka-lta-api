<?php

namespace LukaLtaApi\Value\Blog;

use DateTimeImmutable;
use LukaLtaApi\Value\User\User;
use Ramsey\Uuid\Uuid;

class BlogPost
{
    private function __construct(
        private readonly string            $blogId,
        private readonly User              $user,
        private string                     $title,
        private ?string                    $excerpt,
        private BlogContent                $content,
        private bool                       $isPublished,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable         $updatedAt,
    ) {
    }

    public static function create(
        User               $user,
        string             $title,
        ?string            $excerpt,
        string             $content,
        bool               $isPublished,
        DateTimeImmutable  $createdAt,
        ?DateTimeImmutable $updatedAt = null
    ): self {
        return new self(
            Uuid::uuid4()->toString(),
            $user,
            $title,
            $excerpt,
            BlogContent::fromRaw($content),
            $isPublished,
            $createdAt,
            $updatedAt
        );
    }

    public static function from(
        string             $blogId,
        User               $user,
        string             $title,
        ?string            $excerpt,
        string             $content,
        bool               $isPublished,
        DateTimeImmutable  $createdAt,
        ?DateTimeImmutable $updatedAt = null
    ): self {
        return new self(
            $blogId,
            $user,
            $title,
            $excerpt,
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
            User::fromDatabase($row),
            $row['title'],
            $row['excerpt'],
            BlogContent::fromRaw($row['content']),
            $row['is_published'],
            new DateTimeImmutable($row['created_at']),
            isset($row['updated_at']) ? new DateTimeImmutable($row['updated_at']) : null
        );
    }

    public function toArray(): array
    {
        return [
            'blogId' => $this->getBlogId(),
            'user' => $this->getUser()->toArray(),
            'title' => $this->getTitle(),
            'excerpt' => $this->getExcerpt(),
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
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

    public function setExcerpt(?string $excerpt): void
    {
        $this->excerpt = $excerpt;
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
