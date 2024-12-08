<?php

namespace LukaLtaApi\Slim\Middleware;

use Fig\Http\Message\StatusCodeInterface;
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
    public function __construct()
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$request->hasHeader('Authorization')) {
            return $this->denieRequest(
                'Authorization header is missing'
            );
        }

        $jwt = $request->getHeader('Authorization')[0];

        if (empty($jwt)) {
            return $this->denieRequest(
                'Authorization header is empty'
            );
        }

        if (!Token::validate($jwt, getenv('JWT_SECRET'))) {
            return $this->denieRequest(
                'The JWT is not valid'
            );
        }

        if (!Token::validateExpiration($jwt)) {
            return $this->denieRequest(
                'The JWT has expired'
            );
        }

        $payload = Token::getPayload($jwt);

        if (empty($payload['sub']) === false) {
            $request = $request->withAttribute('userId', $payload['sub']);
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
