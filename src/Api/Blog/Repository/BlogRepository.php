<?php

namespace LukaLtaApi\Api\Blog\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Blog\BlogPost;
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
            INSERT INTO blog_posts (blog_id, user_id, title, content, created_at)
            VALUES (:blog_id, :user_id, :title, :content, NOW())
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'blog_id' => $blogPost->getBlogId(),
                'user_id' => $blogPost->getUserId()->asInt(),
                'title' => $blogPost->getTitle(),
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

    public function updateBlog(BlogPost $blogPost): void
    {
        $sql = <<<SQL
            UPDATE blog_posts
            SET title = :title, content = :content, updated_at = NOW()
            WHERE blog_id = :blog_id AND user_id = :user_id
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'blog_id' => $blogPost->getBlogId(),
                'user_id' => $blogPost->getUserId()->asInt(),
                'title' => $blogPost->getTitle(),
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

    public function getBlogById(string $blogId): ?BlogPost
    {
        $sql = <<<SQL
            SELECT blog_id, user_id, title, content, created_at, updated_at
            FROM blog_posts
            WHERE blog_id = :blog_id
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
