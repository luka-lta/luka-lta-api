<?php

namespace LukaLtaApi\Slim;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CorsResponseManager
{
    public function withCors(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            $response = $response->withStatus(200);
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', '*')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}
