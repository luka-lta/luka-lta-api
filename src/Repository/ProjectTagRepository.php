<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Exception\ProjectTagNotFoundException;
use LukaLtaApi\Value\Project\ProjectId;
use LukaLtaApi\Value\Project\Tag\ProjectTag;
use LukaLtaApi\Value\Project\Tag\ProjectTagId;
use LukaLtaApi\Value\Project\Tag\ProjectTagName;
use LukaLtaApi\Value\Project\Tag\ProjectTagSlug;
use LukaLtaApi\Value\Project\Tag\ProjectTags;
use PDO;
use PDOException;

class ProjectTagRepository
{
    private const string TAG_SELECT = 'SELECT t.tag_id, t.name, t.slug, t.created_at FROM project_tags t';

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getAll(): ProjectTags
    {
        $sql = self::TAG_SELECT . ' ORDER BY t.name ASC';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            $tags = [];
            foreach ($stmt as $row) {
                $tags[] = ProjectTag::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch project tags.', previous: $exception);
        }

        return ProjectTags::from(...$tags);
    }

    public function getById(ProjectTagId $tagId): ?ProjectTag
    {
        $sql = self::TAG_SELECT . ' WHERE t.tag_id = :tag_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['tag_id' => $tagId->asInt()]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch project tag.', previous: $exception);
        }

        return $row !== false ? ProjectTag::fromDatabase($row) : null;
    }

    public function getBySlug(ProjectTagSlug $slug): ?ProjectTag
    {
        $sql = self::TAG_SELECT . ' WHERE t.slug = :slug';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['slug' => (string) $slug]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch project tag by slug.', previous: $exception);
        }

        return $row !== false ? ProjectTag::fromDatabase($row) : null;
    }

    public function getByName(ProjectTagName $name): ?ProjectTag
    {
        $sql = self::TAG_SELECT . ' WHERE t.name = :name';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['name' => (string) $name]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch project tag by name.', previous: $exception);
        }

        return $row !== false ? ProjectTag::fromDatabase($row) : null;
    }

    public function create(ProjectTag $tag): ProjectTag
    {
        $sql = <<<SQL
            INSERT INTO project_tags (name, slug)
            VALUES (:name, :slug)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'name' => (string) $tag->getName(),
                'slug' => (string) $tag->getSlug(),
            ]);
            $id = (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to create project tag.', previous: $exception);
        }

        return ProjectTag::fromDatabase([
            'tag_id'     => $id,
            'name'       => (string) $tag->getName(),
            'slug'       => (string) $tag->getSlug(),
            'created_at' => $tag->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function getTagsForProject(ProjectId $projectId): ProjectTags
    {
        $sql = <<<SQL
            SELECT t.tag_id, t.name, t.slug, t.created_at
            FROM project_tags t
            INNER JOIN project_tag_assignments pta ON t.tag_id = pta.tag_id
            WHERE pta.project_id = :project_id
            ORDER BY t.name ASC
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['project_id' => $projectId->asString()]);

            $tags = [];
            foreach ($stmt as $row) {
                $tags[] = ProjectTag::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch tags for project.', previous: $exception);
        }

        return ProjectTags::from(...$tags);
    }

    public function attachTags(ProjectId $projectId, array $tagIds): void
    {
        if ($tagIds === []) {
            return;
        }

        $sql = 'INSERT IGNORE INTO project_tag_assignments (project_id, tag_id) VALUES (:project_id, :tag_id)';

        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($tagIds as $tagId) {
                $stmt->execute([
                    'project_id' => $projectId->asString(),
                    'tag_id'     => (int) $tagId,
                ]);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to attach project tags.', previous: $exception);
        }
    }

    public function detachTags(ProjectId $projectId): void
    {
        $sql = 'DELETE FROM project_tag_assignments WHERE project_id = :project_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['project_id' => $projectId->asString()]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to detach project tags.', previous: $exception);
        }
    }

    /** Verhindert, dass eine unbekannte Tag-ID als stilles INSERT IGNORE verschwindet. */
    public function assertTagsExist(array $tagIds): void
    {
        foreach ($tagIds as $tagId) {
            if ($this->getById(ProjectTagId::fromInt((int) $tagId)) !== null) {
                continue;
            }

            throw new ProjectTagNotFoundException(sprintf('Project tag %d not found.', (int) $tagId));
        }
    }
}
