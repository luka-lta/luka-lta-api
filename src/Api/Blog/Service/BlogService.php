<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Blog\Service;

use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiUserNotExistsException;
use LukaLtaApi\Exception\BlogNotFoundException;
use LukaLtaApi\Repository\BlogRepository;
use LukaLtaApi\Repository\UserRepository;
use LukaLtaApi\Value\Blog\BlogContent;
use LukaLtaApi\Value\Blog\BlogFilter;
use LukaLtaApi\Value\Blog\BlogId;
use LukaLtaApi\Value\Blog\BlogPost;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\User\UserId;

class BlogService
{
    public function __construct(
        private readonly BlogRepository $repository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function getAllPosts(BlogFilter $filter, bool $isAuthenticated): ApiResult
    {
        $posts = $this->repository->getAll($filter, $isAuthenticated);

        foreach ($posts as $post) {
            $tags = $this->repository->getTagsForPost(BlogId::fromString($post->getBlogId()));
            $post->setTags($tags);
        }

        return ApiResult::from(
            JsonResult::from('Blog posts fetched.', ['posts' => $posts->toArray()])
        );
    }

    public function getPost(BlogId $blogId, bool $isAuthenticated): ApiResult
    {
        $post = $this->repository->getById($blogId, $isAuthenticated);

        if ($post === null) {
            throw new BlogNotFoundException();
        }

        $tags = $this->repository->getTagsForPost($blogId);
        $post->setTags($tags);

        return ApiResult::from(
            JsonResult::from('Blog post fetched.', ['post' => $post->toArray()])
        );
    }

    public function createPost(array $data, int $userId): ApiResult
    {
        $user = $this->userRepository->findById(UserId::fromInt($userId));

        if ($user === null) {
            throw new ApiUserNotExistsException('Authenticated user not found.', 401);
        }

        $post = BlogPost::create(
            $user,
            $data['title'],
            $data['excerpt'] ?? null,
            $data['content'],
            false,
            new DateTimeImmutable(),
        );

        $created = $this->repository->create($post);

        $tagIds = $data['tag_ids'] ?? [];
        if (!empty($tagIds)) {
            $this->repository->attachTags(BlogId::fromString($created->getBlogId()), $tagIds);
        }

        $tags = $this->repository->getTagsForPost(BlogId::fromString($created->getBlogId()));
        $created->setTags($tags);

        return ApiResult::from(
            JsonResult::from('Blog post created.', ['post' => $created->toArray()]),
            StatusCodeInterface::STATUS_CREATED
        );
    }

    public function updatePost(BlogId $blogId, array $data): ApiResult
    {
        $post = $this->repository->getById($blogId, true);

        if ($post === null) {
            throw new BlogNotFoundException();
        }

        if (isset($data['title'])) {
            $post->setTitle($data['title']);
        }

        if (array_key_exists('excerpt', $data)) {
            $post->setExcerpt($data['excerpt']);
        }

        if (isset($data['content'])) {
            $post->setContent(BlogContent::fromRaw($data['content']));
        }

        $post->setUpdatedAt(new DateTimeImmutable());
        $this->repository->update($post);

        if (isset($data['tag_ids'])) {
            $this->repository->detachTags($blogId);
            $this->repository->attachTags($blogId, $data['tag_ids']);
        }

        $tags = $this->repository->getTagsForPost($blogId);
        $post->setTags($tags);

        return ApiResult::from(
            JsonResult::from('Blog post updated.', ['post' => $post->toArray()])
        );
    }

    public function deletePost(BlogId $blogId): ApiResult
    {
        $post = $this->repository->getById($blogId, true);

        if ($post === null) {
            throw new BlogNotFoundException();
        }

        $this->repository->delete($blogId);

        return ApiResult::from(
            JsonResult::from('Blog post deleted.'),
            StatusCodeInterface::STATUS_NO_CONTENT
        );
    }

    public function togglePublish(BlogId $blogId, bool $publish): ApiResult
    {
        $post = $this->repository->getById($blogId, true);

        if ($post === null) {
            throw new BlogNotFoundException();
        }

        $this->repository->publish($blogId, $publish);

        $message = $publish ? 'Blog post published.' : 'Blog post unpublished.';

        return ApiResult::from(JsonResult::from($message));
    }
}
