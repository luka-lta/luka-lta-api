<?php

namespace LukaLtaApi\Slim\Middleware;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\EnvironmentRepository;
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
        private readonly EnvironmentRepository $envRepository,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authHeader = $request->getHeader('Authorization');
        $originHeader = $request->getHeaderLine('Origin');

        if (empty($authHeader) || empty($originHeader)) {
            return $this->denieRequest('Missing Authorization or API Key header');
        }

        return $this->processJwt($authHeader[0], $request, $handler);
    }

    private function processJwt(
        string                  $jwt,
        ServerRequestInterface  $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if (empty($jwt)) {
            return $this->denieRequest('Authorization header is empty');
        }

        if (!Token::validate($jwt, $this->envRepository->get('JWT_SECRET'))) {
            return $this->denieRequest('The JWT is not valid');
        }

        if (!Token::validateExpiration($jwt)) {
            return $this->denieRequest('The JWT has expired');
        }

        $payload = Token::getPayload($jwt);
        if (!empty($payload['sub'])) {
            $request = $request->withAttribute('userId', $payload['sub']);
            $request = $request->withAttribute('authType', 'jwt');
        }

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
