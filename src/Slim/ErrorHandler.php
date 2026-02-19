<?php

namespace LukaLtaApi\Slim;

use LukaLtaApi\Exception\ApiException;
use LukaLtaApi\Value\Misc\AppEnv;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Throwable;

class ErrorHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AppEnv $env,
    ) {
    }

    public function handleError(
        Throwable              $exception,
        ResponseInterface      $response,
        ServerRequestInterface $request,
        bool                   $displayErrorDetails,
    ): ResponseInterface {
        $this->logError($exception, $request);

        $statusCode = 500;
        $errorMessage = 'Internal server error';

        switch ($exception::class) {
            case HttpMethodNotAllowedException::class:
                $statusCode = 405;
                $errorMessage = 'Method not allowed';
                break;
            case HttpNotFoundException::class:
                $statusCode = 404;
                $errorMessage = 'Not found';
                break;
        }

        $payload = [
            'status' => 'error',
            'error' => 'App env is neither production nor development, no errors logged',
        ];

        if ($this->env->matches(AppEnv::ENV_DEVELOPMENT)) {
            $payload = [
                'status' => $statusCode,
                'class' => $exception::class,
                'error' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace(),
            ];
        }

        if ($this->env->matches(AppEnv::ENV_PRODUCTION)) {
            $payload = [
                'status' => $statusCode,
            ];
            if ($displayErrorDetails) {
                $payload['error'] = $errorMessage;
            }
        }

        try {
            $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);
            $response->getBody()->write($jsonPayload);
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), ['error' => $e]);
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function logError(Throwable $exception, ServerRequestInterface $request): void
    {
        $uri = $request->getUri();

        $context = [
            'error' => $exception,
            'method' => $request->getMethod(),
            'uri' => $uri->getPath(),
            'query' => $uri->getQuery(),
        ];

        if ($exception instanceof ApiException && $exception->hasContext()) {
            $context = array_merge($context, $exception->getContext());
        }

        switch ($exception::class) {
            case HttpMethodNotAllowedException::class:
                $this->logger->warning('Api called with forbidden method', $context);
                break;
            case HttpNotFoundException::class:
                $this->logger->warning('Api called with invalid path', $context);
                break;
            default:
                $this->logger->error($exception->getMessage(), $context);
        }
    }
}
