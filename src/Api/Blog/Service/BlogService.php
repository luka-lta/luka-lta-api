<?php

namespace LukaLtaApi\Api\Blog\Service;

use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Api\Blog\Repository\BlogRepository;
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
    ) {
    }

    public function createBlog(ServerRequestInterface $request): ApiResult
    {
        $data = $request->getParsedBody();
        $userId = UserId::fromString($request->getAttribute('userId'));

        $blogPost = BlogPost::create(
            $userId,
            $data['title'],
            $data['content'],
            new DateTimeImmutable()
        );

        var_dump($blogPost->getContent());

        $this->blogRepository->createBlog($blogPost);

        return ApiResult::from(JsonResult::from('Blog post created successfully.'));
    }

    public function updateBlog(ServerRequestInterface $request): ApiResult
    {
        $data = $request->getParsedBody();
        $userId = UserId::fromString($request->getAttribute('userId'));
        $blogId = $request->getAttribute('blogId');

        $blogPost = $this->blogRepository->getBlogById($blogId);

        if ($blogPost === null || $blogPost->getUserId()->asInt() !== $userId->asInt()) {
            return ApiResult::from(
                JsonResult::from(
                    'Blog post not found or access denied.'
                ),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        $blogPost->setTitle($data['title']);
        $blogPost->setContent(BlogContent::fromRaw($data['content']));

        $this->blogRepository->updateBlog($blogPost);

        return ApiResult::from(JsonResult::from('Blog post updated successfully.'));
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
            'blog' => $blogs->toFrontend()
        ]));
    }
}
