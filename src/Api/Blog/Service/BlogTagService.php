<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Blog\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\TagNotFoundException;
use LukaLtaApi\Repository\BlogTagRepository;
use LukaLtaApi\Value\Blog\Tag\Tag;
use LukaLtaApi\Value\Blog\Tag\TagId;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;

class BlogTagService
{
    public function __construct(
        private readonly BlogTagRepository $repository,
    ) {
    }

    public function getTags(): ApiResult
    {
        $tags = $this->repository->getAll();

        return ApiResult::from(
            JsonResult::from('Tags fetched.', ['tags' => $tags->toArray()])
        );
    }

    public function createTag(string $name): ApiResult
    {
        $tag     = Tag::create($name);
        $created = $this->repository->create($tag);

        return ApiResult::from(
            JsonResult::from('Tag created.', ['tag' => $created->toArray()]),
            StatusCodeInterface::STATUS_CREATED
        );
    }

    public function deleteTag(TagId $tagId): ApiResult
    {
        $tag = $this->repository->getById($tagId);

        if ($tag === null) {
            throw new TagNotFoundException();
        }

        $this->repository->delete($tagId);

        return ApiResult::from(
            JsonResult::from('Tag deleted.'),
            StatusCodeInterface::STATUS_NO_CONTENT
        );
    }
}
