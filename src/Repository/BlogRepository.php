<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Blog\BlogFilter;
use LukaLtaApi\Value\Blog\BlogId;
use LukaLtaApi\Value\Blog\BlogPost;
use LukaLtaApi\Value\Blog\BlogPosts;
use LukaLtaApi\Value\Blog\Tag\Tags;
use PDO;
use PDOException;

class BlogRepository
{
    private const BLOG_SELECT = <<<SQL
        SELECT
            bp.blog_id,
            bp.title,
            bp.excerpt,
            bp.content,
            bp.is_published,
            bp.created_at  AS blog_created_at,
            bp.updated_at  AS blog_updated_at,
            u.user_id,
            u.username,
            u.email,
            u.password,
            u.avatar_url,
            u.is_active,
            u.last_active,
            u.created_at,
            u.updated_at
        FROM blog_posts bp
        INNER JOIN users u ON bp.user_id = u.user_id
    SQL;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(BlogPost $post): BlogPost
    {
        $sql = <<<SQL
            INSERT INTO blog_posts (blog_id, user_id, title, excerpt, content, is_published, created_at)
            VALUES (:blog_id, :user_id, :title, :excerpt, :content, :is_published, NOW())
        SQL;

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'blog_id'      => $post->getBlogId(),
                'user_id'      => $post->getUser()->getUserId()?->asInt(),
                'title'        => $post->getTitle(),
                'excerpt'      => $post->getExcerpt(),
                'content'      => $post->getContent()->getContent(),
                'is_published' => $post->isPublished() ? 1 : 0,
            ]);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new ApiDatabaseException('Failed to create blog post.', previous: $exception);
        }

        return $post;
    }

    public function getById(BlogId $blogId, bool $includeUnpublished = false): ?BlogPost
    {
        $sql = self::BLOG_SELECT . ' WHERE bp.blog_id = :blog_id';

        if (!$includeUnpublished) {
            $sql .= ' AND bp.is_published = 1';
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['blog_id' => $blogId->asString()]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch blog post.', previous: $exception);
        }

        if ($row === false) {
            return null;
        }

        return BlogPost::fromDatabase($row);
    }

    public function getAll(BlogFilter $filter, bool $includeUnpublished = false): BlogPosts
    {
        [$conditions, $params] = $this->buildWhereConditions($filter, $includeUnpublished);

        $sql  = self::BLOG_SELECT;
        $sql .= empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $sql .= $this->buildOrderBy($filter);
        $sql .= ' LIMIT :pageSize OFFSET :offset';

        $params['pageSize'] = $filter->getPageSize();
        $params['offset']   = $filter->getOffset();

        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($key, $value, $type);
            }
            $stmt->execute();

            $posts = [];
            foreach ($stmt as $row) {
                $posts[] = BlogPost::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch blog posts.', previous: $exception);
        }

        return BlogPosts::from(...$posts);
    }

    private function buildWhereConditions(BlogFilter $filter, bool $includeUnpublished): array
    {
        $conditions = [];
        $params     = [];

        if (!$includeUnpublished) {
            $conditions[] = 'bp.is_published = 1';
        }

        $queryParams = $filter->getQueryParameter();
        if (!empty($queryParams['title'])) {
            $conditions[]    = 'bp.title LIKE :title';
            $params['title'] = '%' . $queryParams['title'] . '%';
        }

        $tagId = $filter->getTagId();
        if ($tagId !== null) {
            $conditions[]      = 'bp.blog_id IN (SELECT blog_id FROM blog_post_tags WHERE tag_id = :tag_id)';
            $params['tag_id']  = $tagId;
        }

        return [$conditions, $params];
    }

    private function buildOrderBy(BlogFilter $filter): string
    {
        $sortColumn    = $filter->getSortColumn();
        $sortDirection = $filter->getSortDirection();
        $allowedColumns = ['bp.created_at', 'bp.title', 'bp.updated_at'];

        if ($sortColumn !== null && $sortDirection !== null && in_array($sortColumn, $allowedColumns, true)) {
            return " ORDER BY {$sortColumn} {$sortDirection}";
        }

        return ' ORDER BY bp.created_at DESC';
    }

    public function update(BlogPost $post): BlogPost
    {
        $sql = <<<SQL
            UPDATE blog_posts
            SET title        = :title,
                excerpt      = :excerpt,
                content      = :content,
                is_published = :is_published,
                updated_at   = NOW()
            WHERE blog_id = :blog_id
        SQL;

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'title'        => $post->getTitle(),
                'excerpt'      => $post->getExcerpt(),
                'content'      => $post->getContent()->getContent(),
                'is_published' => $post->isPublished() ? 1 : 0,
                'blog_id'      => $post->getBlogId(),
            ]);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new ApiDatabaseException('Failed to update blog post.', previous: $exception);
        }

        return $post;
    }

    public function delete(BlogId $blogId): void
    {
        $sql = 'DELETE FROM blog_posts WHERE blog_id = :blog_id';

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['blog_id' => $blogId->asString()]);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new ApiDatabaseException('Failed to delete blog post.', previous: $exception);
        }
    }

    public function publish(BlogId $blogId, bool $published): void
    {
        $sql = 'UPDATE blog_posts SET is_published = :is_published, updated_at = NOW() WHERE blog_id = :blog_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'is_published' => $published ? 1 : 0,
                'blog_id'      => $blogId->asString(),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to update publish status.', previous: $exception);
        }
    }

    public function attachTags(BlogId $blogId, array $tagIds): void
    {
        if (empty($tagIds)) {
            return;
        }

        $sql = 'INSERT IGNORE INTO blog_post_tags (blog_id, tag_id) VALUES (:blog_id, :tag_id)';

        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($tagIds as $tagId) {
                $stmt->execute(['blog_id' => $blogId->asString(), 'tag_id' => (int) $tagId]);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to attach tags.', previous: $exception);
        }
    }

    public function detachTags(BlogId $blogId): void
    {
        $sql = 'DELETE FROM blog_post_tags WHERE blog_id = :blog_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['blog_id' => $blogId->asString()]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to detach tags.', previous: $exception);
        }
    }

    public function getTagsForPost(BlogId $blogId): Tags
    {
        $sql = <<<SQL
            SELECT t.tag_id, t.name, t.slug, t.created_at
            FROM blog_tags t
            INNER JOIN blog_post_tags bpt ON t.tag_id = bpt.tag_id
            WHERE bpt.blog_id = :blog_id
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['blog_id' => $blogId->asString()]);

            $tags = [];
            foreach ($stmt as $row) {
                $tags[] = \LukaLtaApi\Value\Blog\Tag\Tag::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch tags for post.', previous: $exception);
        }

        return Tags::from(...$tags);
    }
}
