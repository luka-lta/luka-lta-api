<?php

declare(strict_types=1);

namespace LukaLtaApi\Slim\Middleware;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\ApiKeyRepository;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class ApiKeyPermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ApiKeyRepository $apiKeyRepository,
        private readonly string           $requiredPermission,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $apiKeyHeader = $request->getHeaderLine('X-API-Key');
        $originHeader = $request->getHeaderLine('Origin');

        if (empty($apiKeyHeader) || empty($originHeader)) {
            return $this->denyRequest('Missing X-API-Key or Origin header.', StatusCodeInterface::STATUS_UNAUTHORIZED);
        }

        $apiKey = $this->apiKeyRepository->findActiveByHashedKey(hash('sha256', $apiKeyHeader));

        if ($apiKey === null) {
            return $this->denyRequest('Invalid or expired API key.', StatusCodeInterface::STATUS_UNAUTHORIZED);
        }

        if ($apiKey->getOrigin() !== $originHeader) {
            return $this->denyRequest('API key is not valid for this origin.', StatusCodeInterface::STATUS_FORBIDDEN);
        }

        if (!$this->apiKeyRepository->hasPermission($apiKey->getKeyId(), $this->requiredPermission)) {
            return $this->denyRequest(
                'API key lacks the required permission.',
                StatusCodeInterface::STATUS_FORBIDDEN,
            );
        }

        $request = $request->withAttribute('authType', 'apiKey');
        $request = $request->withAttribute('apiKeyId', $apiKey->getKeyId()->asInt());

        return $handler->handle($request);
    }

    private function denyRequest(string $errorMessage, int $statusCode): ResponseInterface
    {
        $response = (new ResponseFactory())->createResponse();

        return ApiResult::from(
            JsonResult::from($errorMessage),
            $statusCode,
        )->getResponse($response);
    }
}
