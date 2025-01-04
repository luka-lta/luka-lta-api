<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\User\Avatar;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetAvatarAction extends ApiAction
{
    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $uploadDir = '/app/uploads/profile-pictures/';
        $filename = $request->getAttribute('filename');

        $file = $uploadDir . $filename;

        if (!file_exists($file)) {
            return ApiResult::from(
                JsonResult::from('File not found'),
                StatusCodeInterface::STATUS_NOT_FOUND
            )->getResponse($response);
        }

        $mimeType = mime_content_type($file);
        $response->getBody()->write(file_get_contents($file));

        return $response
            ->withHeader('Content-Type', $mimeType);
    }
}
