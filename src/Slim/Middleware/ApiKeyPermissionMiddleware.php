<?php

declare(strict_types=1);

namespace LukaLtaApi\Slim\Middleware;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Service\PermissionService;
use LukaLtaApi\Value\ApiKey\KeyId;
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
        private readonly PermissionService $service,
        private readonly array $permissions
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authType = $request->getAttribute('authType');

        if ($authType !== 'apiKey') {
            return $handler->handle($request);
        }

        $apiKeyId = KeyId::fromInt($request->getAttribute('apiKeyId'));

        if (!$this->service->hasAccess($apiKeyId, $this->permissions)) {
            $response = (new ResponseFactory())->createResponse();
            return ApiResult::from(
                JsonResult::from('Insufficient permissions'),
                StatusCodeInterface::STATUS_FORBIDDEN
            )->getResponse($response);
        }

        return $handler->handle($request);
    }
}
