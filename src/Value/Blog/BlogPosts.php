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
                'id' => $blogPost->getBlogId(),
                'title' => $blogPost->getTitle(),
                'content' => $blogPost->getContent()->toHtml(),
                'userId' => $blogPost->getUserId()->asInt(),
                'createdAt' => $blogPost->getCreatedAt()->format('Y-m-d H:i:s'),
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
