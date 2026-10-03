<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Project\Project;
use LukaLtaApi\Value\Project\ProjectId;
use LukaLtaApi\Value\Project\Projects;
use LukaLtaApi\Value\Project\ProjectSlug;
use PDO;
use PDOException;

class ProjectRepository
{
    private const string PROJECT_SELECT = <<<SQL
        SELECT
            p.project_id,
            p.name,
            p.slug,
            p.short_description,
            p.description,
            p.status,
            p.is_visible,
            p.category,
            p.tech_stack,
            p.website_url,
            p.live_label,
            p.repository_url,
            p.repository_owner,
            p.repository_name,
            p.demo_url,
            p.documentation_url,
            p.role,
            p.project_year,
            p.is_client_project,
            p.metadata,
            p.sort_order,
            p.created_at,
            p.updated_at
        FROM projects p
    SQL;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getAll(bool $onlyVisible): Projects
    {
        $sql = self::PROJECT_SELECT;

        if ($onlyVisible) {
            $sql .= ' WHERE p.is_visible = 1';
        }

        $sql .= ' ORDER BY p.sort_order ASC, p.created_at ASC';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            $projects = [];
            foreach ($stmt as $row) {
                $projects[] = Project::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch projects.', previous: $exception);
        }

        return Projects::from(...$projects);
    }

    public function getById(ProjectId $projectId): ?Project
    {
        $sql = self::PROJECT_SELECT . ' WHERE p.project_id = :project_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['project_id' => $projectId->asString()]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch project.', previous: $exception);
        }

        return $row !== false ? Project::fromDatabase($row) : null;
    }

    public function getBySlug(ProjectSlug $slug, bool $onlyVisible): ?Project
    {
        $sql = self::PROJECT_SELECT . ' WHERE p.slug = :slug';

        if ($onlyVisible) {
            $sql .= ' AND p.is_visible = 1';
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['slug' => $slug->asString()]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch project by slug.', previous: $exception);
        }

        return $row !== false ? Project::fromDatabase($row) : null;
    }

    public function create(Project $project): Project
    {
        $sql = <<<SQL
            INSERT INTO projects (
                project_id, name, slug, short_description, description, status, is_visible,
                category, tech_stack, website_url, live_label, repository_url, repository_owner,
                repository_name, demo_url, documentation_url, role, project_year,
                is_client_project, metadata, sort_order
            ) VALUES (
                :project_id, :name, :slug, :short_description, :description, :status, :is_visible,
                :category, :tech_stack, :website_url, :live_label, :repository_url, :repository_owner,
                :repository_name, :demo_url, :documentation_url, :role, :project_year,
                :is_client_project, :metadata, :sort_order
            )
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($project->toDatabaseRow());
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to create project.', previous: $exception);
        }

        return $project;
    }

    public function update(Project $project): void
    {
        $sql = <<<SQL
            UPDATE projects SET
                name              = :name,
                slug              = :slug,
                short_description = :short_description,
                description       = :description,
                status            = :status,
                is_visible        = :is_visible,
                category          = :category,
                tech_stack        = :tech_stack,
                website_url       = :website_url,
                live_label        = :live_label,
                repository_url    = :repository_url,
                repository_owner  = :repository_owner,
                repository_name   = :repository_name,
                demo_url          = :demo_url,
                documentation_url = :documentation_url,
                role              = :role,
                project_year      = :project_year,
                is_client_project = :is_client_project,
                metadata          = :metadata,
                sort_order        = :sort_order
            WHERE project_id = :project_id
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($project->toDatabaseRow());
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to update project.', previous: $exception);
        }
    }

    public function delete(ProjectId $projectId): void
    {
        $sql = 'DELETE FROM projects WHERE project_id = :project_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['project_id' => $projectId->asString()]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to delete project.', previous: $exception);
        }
    }

    /** @param array<int, array{projectId: string, sortOrder: int}> $pairs */
    public function updateSortOrder(array $pairs): void
    {
        $sql = 'UPDATE projects SET sort_order = :sort_order WHERE project_id = :project_id';

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            foreach ($pairs as $pair) {
                $stmt->execute([
                    'sort_order' => $pair['sortOrder'],
                    'project_id' => $pair['projectId'],
                ]);
            }
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new ApiDatabaseException('Failed to update project sort order.', previous: $exception);
        }
    }

    public function getNextSortOrder(): int
    {
        $sql = 'SELECT COALESCE(MAX(p.sort_order), -1) + 1 AS next_order FROM projects p';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to determine next sort order.', previous: $exception);
        }

        return (int) $row['next_order'];
    }
}
