<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Blog\Tag\Tag;
use LukaLtaApi\Value\Blog\Tag\TagId;
use LukaLtaApi\Value\Blog\Tag\TagSlug;
use LukaLtaApi\Value\Blog\Tag\Tags;
use PDO;
use PDOException;

class BlogTagRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(Tag $tag): Tag
    {
        $sql = <<<SQL
            INSERT INTO blog_tags (name, slug)
            VALUES (:name, :slug)
        SQL;

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'name' => (string) $tag->getName(),
                'slug' => (string) $tag->getSlug(),
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new ApiDatabaseException('Failed to create tag.', previous: $exception);
        }

        return Tag::fromDatabase([
            'tag_id'     => $id,
            'name'       => (string) $tag->getName(),
            'slug'       => (string) $tag->getSlug(),
            'created_at' => $tag->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function getById(TagId $tagId): ?Tag
    {
        $sql = 'SELECT * FROM blog_tags WHERE tag_id = :tag_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['tag_id' => $tagId->asInt()]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch tag.', previous: $exception);
        }

        return $row !== false ? Tag::fromDatabase($row) : null;
    }

    public function getBySlug(TagSlug $slug): ?Tag
    {
        $sql = 'SELECT * FROM blog_tags WHERE slug = :slug';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['slug' => (string) $slug]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch tag by slug.', previous: $exception);
        }

        return $row !== false ? Tag::fromDatabase($row) : null;
    }

    public function getAll(): Tags
    {
        $sql = 'SELECT * FROM blog_tags ORDER BY name ASC';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            $tags = [];
            foreach ($stmt as $row) {
                $tags[] = Tag::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch tags.', previous: $exception);
        }

        return Tags::from(...$tags);
    }

    public function delete(TagId $tagId): void
    {
        $sql = 'DELETE FROM blog_tags WHERE tag_id = :tag_id';

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['tag_id' => $tagId->asInt()]);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new ApiDatabaseException('Failed to delete tag.', previous: $exception);
        }
    }
}
