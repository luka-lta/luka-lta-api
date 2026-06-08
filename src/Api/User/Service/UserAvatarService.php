<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\User\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\S3Repository;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\User\UserId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserAvatarService
{
    public function __construct(
        private readonly S3Repository $s3Repository,
    ) {
    }

    public function getAvatar(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = UserId::fromString($request->getAttribute('userId'));
        $fileData = $this->s3Repository->getAvatarImageFromS3($userId);

        if (!$fileData) {
            return ApiResult::from(
                JsonResult::from('Avatar not found'),
                StatusCodeInterface::STATUS_NOT_FOUND
            )->getResponse($response);
        }

        $response->getBody()->write($fileData['body']);

        return $response
            ->withHeader('Content-Type', $fileData['contentType']);
    }
}
