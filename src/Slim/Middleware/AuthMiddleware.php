<?php

namespace LukaLtaApi\Slim\Middleware;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\ApiKeyRepository;
use LukaLtaApi\Value\ApiKey\KeyOrigin;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReallySimpleJWT\Token;
use Slim\Psr7\Factory\ResponseFactory;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ApiKeyRepository $apiKeyRepository
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authHeader = $request->getHeader('Authorization');
        $apiKeyHeader = $request->getHeaderLine('X-API-Key');
        $originHeader = $request->getHeaderLine('Origin');

        if (empty($authHeader) && (empty($apiKeyHeader) || empty($originHeader))) {
            return $this->denieRequest('Missing Authorization or API Key header');
        }

        if (!empty($authHeader)) {
            return $this->processJwt($authHeader[0], $request, $handler);
        }

        return $this->validateApiKey($request, $handler);
    }

    private function processJwt(
        string $jwt,
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if (empty($jwt)) {
            return $this->denieRequest('Authorization header is empty');
        }

        if (!Token::validate($jwt, getenv('JWT_SECRET'))) {
            return $this->denieRequest('The JWT is not valid');
        }

        if (!Token::validateExpiration($jwt)) {
            return $this->denieRequest('The JWT has expired');
        }

        $payload = Token::getPayload($jwt);
        if (!empty($payload['sub'])) {
            $request = $request->withAttribute('userId', $payload['sub']);
        }

        return $handler->handle($request);
    }

    private function validateApiKey(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $apiKeyHeader = $request->getHeaderLine('X-API-Key');
        $originHeader = $request->getHeaderLine('Origin');

        if (empty($apiKeyHeader) || empty($originHeader)) {
            return $this->denieRequest('The API Key or Origin header is empty');
        }

        $keyOrigin = KeyOrigin::fromString($originHeader);
        $apiKey = $this->apiKeyRepository->getApiKeyByOrigin($keyOrigin);

        if (!$apiKey || !(string)$apiKey->getApiKey() === $apiKeyHeader || !$apiKey->isValid()) {
            return $this->denieRequest('The API Key is not valid or expired');
        }

        $request = $request->withAttribute('userId', $apiKey->getCreatedBy()->asInt());
        return $handler->handle($request);
    }

    private function denieRequest(string $errorMessage): ResponseInterface
    {
        $response = (new ResponseFactory())->createResponse();
        return ApiResult::from(
            JsonResult::from($errorMessage),
            StatusCodeInterface::STATUS_UNAUTHORIZED
        )->getResponse($response);
    }
}
