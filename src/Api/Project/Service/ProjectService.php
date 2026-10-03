<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiInvalidArgumentException;
use LukaLtaApi\Exception\ProjectNotFoundException;
use LukaLtaApi\Exception\ProjectSlugAlreadyExistsException;
use LukaLtaApi\Repository\EnvironmentRepository;
use LukaLtaApi\Repository\ProjectAssetRepository;
use LukaLtaApi\Repository\ProjectRepository;
use LukaLtaApi\Repository\ProjectTagRepository;
use LukaLtaApi\Value\Project\Project;
use LukaLtaApi\Value\Project\ProjectId;
use LukaLtaApi\Value\Project\ProjectSlug;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;

class ProjectService
{
    /** Maximale Anzahl Tags pro Projekt */
    private const int MAX_TAGS_PER_PROJECT = 20;

    /** Fallback entspricht der Produktions-URL, damit ein fehlendes Env dort nichts bricht. */
    private const string DEFAULT_API_BASE_URL = 'https://api.luka-lta.dev/api/v1';

    public function __construct(
        private readonly ProjectRepository      $repository,
        private readonly ProjectAssetRepository $assetRepository,
        private readonly ProjectTagRepository   $tagRepository,
        private readonly EnvironmentRepository  $environmentRepository,
    ) {
    }

    public function getAssetBaseUrl(): string
    {
        $baseUrl = $this->environmentRepository->get('API_BASE_URL', self::DEFAULT_API_BASE_URL);

        return rtrim((string) $baseUrl, '/') . '/projects';
    }

    public function getAllProjects(bool $onlyVisible): ApiResult
    {
        $projects = $this->repository->getAll($onlyVisible);

        foreach ($projects as $project) {
            $this->hydrate($project);
        }

        return ApiResult::from(
            JsonResult::from('Projects fetched.', ['projects' => $projects->toArray($this->getAssetBaseUrl())])
        );
    }

    public function getProjectBySlug(ProjectSlug $slug): ApiResult
    {
        $project = $this->repository->getBySlug($slug, true);

        if ($project === null) {
            throw new ProjectNotFoundException();
        }

        $this->hydrate($project);

        return ApiResult::from(
            JsonResult::from('Project fetched.', ['project' => $project->toArray($this->getAssetBaseUrl())])
        );
    }

    public function getProjectById(ProjectId $projectId): ApiResult
    {
        $project = $this->loadProjectOrFail($projectId);
        $this->hydrate($project);

        return ApiResult::from(
            JsonResult::from('Project fetched.', ['project' => $project->toArray($this->getAssetBaseUrl())])
        );
    }

    public function createProject(array $data): ApiResult
    {
        $project = Project::create($data);

        $this->assertSlugIsFree($project->getSlug(), null);
        $this->assertTagIdsValid($data);

        if (!isset($data['sortOrder'])) {
            $project->applyChanges(['sortOrder' => $this->repository->getNextSortOrder()]);
        }

        $created = $this->repository->create($project);
        $this->syncTags($created->getProjectId(), $data);
        $this->hydrate($created);

        return ApiResult::from(
            JsonResult::from('Project created.', ['project' => $created->toArray($this->getAssetBaseUrl())]),
            StatusCodeInterface::STATUS_CREATED
        );
    }

    public function updateProject(ProjectId $projectId, array $data): ApiResult
    {
        $project = $this->loadProjectOrFail($projectId);
        $project->applyChanges($data);

        $this->assertSlugIsFree($project->getSlug(), $projectId);
        $this->assertTagIdsValid($data);

        $this->repository->update($project);
        $this->syncTags($projectId, $data);
        $this->hydrate($project);

        return ApiResult::from(
            JsonResult::from('Project updated.', ['project' => $project->toArray($this->getAssetBaseUrl())])
        );
    }

    public function deleteProject(ProjectId $projectId): ApiResult
    {
        $this->loadProjectOrFail($projectId);

        $this->repository->delete($projectId);

        return ApiResult::from(
            JsonResult::from('Project deleted.'),
            StatusCodeInterface::STATUS_NO_CONTENT
        );
    }

    public function reorderProjects(array $items): ApiResult
    {
        if ($items === []) {
            throw new ApiInvalidArgumentException('Field projects must contain at least one entry.', 400);
        }

        $pairs = [];
        foreach ($items as $item) {
            if (!isset($item['projectId'], $item['sortOrder'])) {
                throw new ApiInvalidArgumentException('Each entry requires projectId and sortOrder.', 400);
            }

            $projectId = ProjectId::fromString((string) $item['projectId']);
            $this->loadProjectOrFail($projectId);

            $pairs[] = [
                'projectId' => $projectId->asString(),
                'sortOrder' => (int) $item['sortOrder'],
            ];
        }

        $this->repository->updateSortOrder($pairs);

        return ApiResult::from(JsonResult::from('Project order updated.'));
    }

    public function loadProjectOrFail(ProjectId $projectId): Project
    {
        $project = $this->repository->getById($projectId);

        if ($project === null) {
            throw new ProjectNotFoundException();
        }

        return $project;
    }

    /** Laedt Tags und Assets nach — beide liegen in eigenen Tabellen. */
    private function hydrate(Project $project): void
    {
        $project->setTags($this->tagRepository->getTagsForProject($project->getProjectId()));
        $project->setAssets($this->assetRepository->getByProject($project->getProjectId()));
    }

    private function assertSlugIsFree(ProjectSlug $slug, ?ProjectId $ignoredProjectId): void
    {
        $existing = $this->repository->getBySlug($slug, false);

        if ($existing === null) {
            return;
        }

        if ($ignoredProjectId !== null && $existing->getProjectId()->asString() === $ignoredProjectId->asString()) {
            return;
        }

        throw new ProjectSlugAlreadyExistsException();
    }

    private function assertTagIdsValid(array $data): void
    {
        if (!array_key_exists('tagIds', $data)) {
            return;
        }

        $tagIds = $data['tagIds'] ?? [];

        if (!is_array($tagIds)) {
            throw new ApiInvalidArgumentException('Field tagIds must be an array of integers.', 400);
        }

        if (count($tagIds) > self::MAX_TAGS_PER_PROJECT) {
            throw new ApiInvalidArgumentException('A project must not have more than 20 tags.', 400);
        }

        $this->tagRepository->assertTagsExist($tagIds);
    }

    private function syncTags(ProjectId $projectId, array $data): void
    {
        if (!array_key_exists('tagIds', $data)) {
            return;
        }

        $tagIds = $data['tagIds'] ?? [];

        $this->tagRepository->detachTags($projectId);
        $this->tagRepository->attachTags($projectId, $tagIds);
    }
}
