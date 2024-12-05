<?php

namespace LukaLtaApi\Api;

use LukaLtaApi\Exception\ApiException;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\ErrorResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

abstract class ApiAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        try {
            $response = $this->execute($request, $response);
        } catch (ApiException $exception) {
            $result = ApiResult::from(
                ErrorResult::from($exception),
                $exception->getCode()
            );

            return $result->getResponse($response);
        } catch (Throwable $exception) {
            $result = ApiResult::from(
                ErrorResult::from($exception),
                500
            );

            return $result->getResponse($response);
        }

        return $response;
    }

    abstract protected function execute(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface;
}
