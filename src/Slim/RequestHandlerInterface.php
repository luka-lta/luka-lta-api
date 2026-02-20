<?php

namespace LukaLtaApi\Slim;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface RequestHandlerInterface
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface;
}
