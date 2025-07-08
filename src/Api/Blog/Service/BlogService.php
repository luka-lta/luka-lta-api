<?php

namespace LukaLtaApi\Api\Blog\Service;

use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Api\Blog\Repository\BlogRepository;
use LukaLtaApi\Repository\UserRepository;
use LukaLtaApi\Value\Blog\BlogContent;
use LukaLtaApi\Value\Blog\BlogPost;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\User\UserId;
use Psr\Http\Message\ServerRequestInterface;

class BlogService
{
    public function __construct(
        private readonly BlogRepository $blogRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function createBlog(ServerRequestInterface $request): ApiResult
    {
        $data = $request->getParsedBody();
        $userId = UserId::fromString($request->getAttribute('userId'));
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            return ApiResult::from(
                JsonResult::from('User not found.'),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        $blogPost = BlogPost::create(
            $user,
            $data['title'],
            $data['excerpt'] ?? null,
            $data['content'],
            $data['isPublished'] ?? false,
            new DateTimeImmutable()
        );

        $this->blogRepository->createBlog($blogPost);

        return ApiResult::from(JsonResult::from('Blog post created successfully.'));
    }

    public function updateBlog(ServerRequestInterface $request): ApiResult
    {
        $data = $request->getParsedBody();
        $userId = UserId::fromString($request->getAttribute('userId'));
        $blogId = $request->getAttribute('blogId');

        $blogPost = $this->blogRepository->getBlogById($blogId);

        if ($blogPost === null || $blogPost->getUser()->getUserId()->asInt() !== $userId->asInt()) {
            return ApiResult::from(
                JsonResult::from(
                    'Blog post not found or access denied.'
                ),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        $blogPost->setTitle($data['title']);
        $blogPost->setExcerpt($data['excerpt'] ?? null);
        $blogPost->setContent(BlogContent::fromRaw($data['content']));
        $blogPost->setIsPublished($data['isPublished']);

        $this->blogRepository->updateBlog($blogPost);

        return ApiResult::from(JsonResult::from('Blog post updated successfully.'));
    }

    public function getBlogById(ServerRequestInterface $request): ApiResult
    {
        $blogId = $request->getAttribute('blogId');
        $blogPost = $this->blogRepository->getBlogById($blogId);

        if ($blogPost === null) {
            return ApiResult::from(
                JsonResult::from('Blog post not found.'),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        return ApiResult::from(JsonResult::from('Blog post found.', [
            'blog' => $blogPost->toArray()
        ]));
    }

    public function getAllBlogs(): ApiResult
    {
        $blogs = $this->blogRepository->getAll();

        if ($blogs->count() === 0) {
            return ApiResult::from(
                JsonResult::from('No blogs found.'),
            );
        }

        return ApiResult::from(JsonResult::from('Blogs found.', [
            'blogs' => $blogs->toFrontend()
        ]));
    }
}
