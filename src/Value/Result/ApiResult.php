<?php

namespace LukaLtaApi\Value\Result;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Slim\ResultInterface;
use Psr\Http\Message\ResponseInterface;

class ApiResult
{
    private function __construct(
        private readonly ResultInterface $result,
        private readonly int $statusCode,
    ) {
    }

    public static function from(
        ResultInterface $result,
        int $statusCode = StatusCodeInterface::STATUS_OK,
    ): ApiResult {
        return new self($result, $statusCode);
    }

    public function getResponse(ResponseInterface $response): ResponseInterface
    {
        $result = $this->result->toArray();
        $result = array_merge(['status' => $this->statusCode], $result);

        $response->getBody()->write(json_encode($result, JSON_THROW_ON_ERROR));

        return $response
            ->withStatus($this->statusCode)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', '*')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}
