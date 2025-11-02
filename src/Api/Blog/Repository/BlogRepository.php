<?php

namespace LukaLtaApi\Api\Blog\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Blog\BlogPost;
use LukaLtaApi\Value\Blog\BlogPosts;
use PDO;
use PDOException;

class BlogRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function createBlog(
        BlogPost $blogPost,
    ): void {
        $sql = <<<SQL
            INSERT INTO blog_posts (blog_id, user_id, title, excerpt, content, is_published, created_at)
            VALUES (:blog_id, :user_id, :title, :content, :is_published, NOW())
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'blog_id' => $blogPost->getBlogId(),
                'user_id' => $blogPost->getUser()->getUserId()->asInt(),
                'title' => $blogPost->getTitle(),
                'content' => $blogPost->getContent()->getContent(),
                'is_published' => (int)$blogPost->isPublished(),
            ]);
        } catch (PDOException $e) {
            throw new ApiDatabaseException(
                $e->getMessage(),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }

    public function updateBlog(BlogPost $blogPost): void
    {
        $sql = <<<SQL
            UPDATE blog_posts
            SET title = :title, excerpt = :excerpt, content = :content, updated_at = NOW(), is_published = :is_published
            WHERE blog_id = :blog_id AND user_id = :user_id
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'blog_id' => $blogPost->getBlogId(),
                'user_id' => $blogPost->getUser()->getUserId()->asInt(),
                'title' => $blogPost->getTitle(),
                'excerpt' => $blogPost->getExcerpt(),
                'is_published' => (int)$blogPost->isPublished(),
                'content' => $blogPost->getContent()->getContent(),
            ]);
        } catch (PDOException $e) {
            throw new ApiDatabaseException(
                $e->getMessage(),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }

    public function getAll(): BlogPosts
    {
        $sql = <<<SQL
            SELECT 
                blog_posts.blog_id,
                blog_posts.user_id,
                blog_posts.title,
                blog_posts.excerpt,
                blog_posts.content,
                blog_posts.is_published,
                blog_posts.created_at,
                blog_posts.updated_at,
        
                users.*
            FROM blog_posts
            INNER JOIN users ON blog_posts.user_id = users.user_id
            ORDER BY blog_posts.created_at DESC
        SQL;


        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            $blogPosts = [];
            foreach ($rows as $row) {
                $blogPosts[] = BlogPost::fromDatabase($row);
            }
        } catch (PDOException $e) {
            throw new ApiDatabaseException(
                $e->getMessage(),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }

        return BlogPosts::from(...$blogPosts);
    }

    public function getBlogById(string $blogId): ?BlogPost
    {
        $sql = <<<SQL
            SELECT 
                blog_posts.blog_id,
                blog_posts.user_id,
                blog_posts.title,
                blog_posts.excerpt,
                blog_posts.content,
                blog_posts.is_published,
                blog_posts.created_at,
                blog_posts.updated_at,
        
                users.*
            FROM blog_posts
            INNER JOIN users ON blog_posts.user_id = users.user_id
            WHERE blog_posts.blog_id = :blog_id
            ORDER BY blog_posts.created_at DESC
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['blog_id' => $blogId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return null;
            }

            return BlogPost::fromDatabase($row);
        } catch (PDOException $e) {
            throw new ApiDatabaseException(
                $e->getMessage(),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }
}
