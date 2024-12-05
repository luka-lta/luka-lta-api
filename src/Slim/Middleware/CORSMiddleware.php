<?php

namespace LukaLtaApi\Slim\Middleware;

use LukaLtaApi\Slim\CorsResponseManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CORSMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return (new CorsResponseManager())->withCors($request, $handler->handle($request));
    }
}
