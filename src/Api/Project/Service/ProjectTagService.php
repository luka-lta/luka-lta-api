<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ProjectTagSlugConflictException;
use LukaLtaApi\Repository\ProjectTagRepository;
use LukaLtaApi\Value\Project\Tag\ProjectTag;
use LukaLtaApi\Value\Project\Tag\ProjectTagName;
use LukaLtaApi\Value\Project\Tag\ProjectTagSlug;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;

class ProjectTagService
{
    public function __construct(
        private readonly ProjectTagRepository $repository,
    ) {
    }

    public function getTags(): ApiResult
    {
        $tags = $this->repository->getAll();

        return ApiResult::from(
            JsonResult::from('Project tags fetched.', ['tags' => $tags->toArray()])
        );
    }

    /**
     * Idempotent: ein bereits existierender Tag wird zurueckgegeben statt als
     * Konflikt abgewiesen. Das KiboUI-"Create a Tag"-Feld im Dashboard schickt
     * beim Tippen eines bekannten Namens sonst unnoetig einen Fehler.
     */
    public function createTag(string $name): ApiResult
    {
        $existing = $this->repository->getByName(ProjectTagName::fromString($name));

        if ($existing !== null) {
            return ApiResult::from(
                JsonResult::from('Project tag already exists.', ['tag' => $existing->toArray()])
            );
        }

        $slug = ProjectTagSlug::fromName($name);

        // Der Slug verwirft Sonderzeichen, deshalb kollidieren z. B. "C", "C#" und
        // "C++". Ein fremder Tag mit demselben Slug ist ein echter Konflikt und
        // darf nicht als Treffer durchgehen — sonst bekaeme der Aufrufer still
        // einen anderen Tag zurueck.
        if ($this->repository->getBySlug($slug) !== null) {
            throw new ProjectTagSlugConflictException(
                sprintf('Another project tag already uses the slug "%s".', (string) $slug),
            );
        }

        $created = $this->repository->create(ProjectTag::create($name));

        return ApiResult::from(
            JsonResult::from('Project tag created.', ['tag' => $created->toArray()]),
            StatusCodeInterface::STATUS_CREATED
        );
    }
}
