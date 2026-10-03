<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\ProjectTagRepository;
use LukaLtaApi\Value\Project\Tag\ProjectTag;
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
        $tag      = ProjectTag::create($name);
        $existing = $this->repository->getBySlug(ProjectTagSlug::fromName($name));

        if ($existing !== null) {
            return ApiResult::from(
                JsonResult::from('Project tag already exists.', ['tag' => $existing->toArray()])
            );
        }

        $created = $this->repository->create($tag);

        return ApiResult::from(
            JsonResult::from('Project tag created.', ['tag' => $created->toArray()]),
            StatusCodeInterface::STATUS_CREATED
        );
    }
}
