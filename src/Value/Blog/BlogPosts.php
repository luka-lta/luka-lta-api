<?php

namespace LukaLtaApi\Value\Blog;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class BlogPosts implements IteratorAggregate, JsonSerializable, Countable
{
    private readonly array $blogPosts;

    private function __construct(BlogPost ...$blogPosts)
    {
        $this->blogPosts = $blogPosts;
    }

    public static function from(BlogPost ...$blogPost): self
    {
        return new self(...$blogPost);
    }

    public function toFrontend(): array
    {
        $blogPosts = [];

        foreach ($this->blogPosts as $blogPost) {
            $blogPosts[] = [
                'blogId' => $blogPost->getBlogId(),
                'title' => $blogPost->getTitle(),
                'excerpt' => $blogPost->getExcerpt(),
                'content' => $blogPost->getContent()->toHtml(),
                'createdAt' => $blogPost->getCreatedAt()->format('Y-m-d H:i:s'),
                'updatedAt' => $blogPost->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'isPublished' => $blogPost->isPublished(),
                'user' => $blogPost->getUser()->toArray(),
            ];
        }

        return $blogPosts;
    }

    public function getIterator(): Generator
    {
        yield from $this->blogPosts;
    }

    public function count(): int
    {
        return count($this->blogPosts);
    }

    public function jsonSerialize(): array
    {
        return  $this->blogPosts;
    }

    public function toArray(): array
    {
        return array_map(static fn($blogPost) => $blogPost->toArray(), $this->blogPosts);
    }
}
