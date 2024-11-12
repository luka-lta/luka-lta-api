<?php

namespace LukaLtaApi\Slim;

use LukaLtaApi\Api\LinkCollection\Create\CreateLinkAction;
use LukaLtaApi\Slim\Middleware\CORSMiddleware;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Throwable;

class RouteMiddlewareCollector
{
    public function register(App $app): void
    {
        $app->addRoutingMiddleware();
        $app->addBodyParsingMiddleware();
        $app->add(new CORSMiddleware());
        $this->registerErrorHandler($app);
        $this->registerPreflight($app);
        $this->registerApiRoutes($app);
        $this->registerNotFoundRoutes($app);
    }

    public function registerErrorHandler(App $app): void
    {
        $container = $app->getContainer();

        $customErrorHandler = function (
            ServerRequestInterface $request,
            Throwable              $exception,
            bool                   $displayErrorDetails,
        ) use (
            $app,
            $container,
        ): ResponseInterface {
            $errorHandler = new ErrorHandler(
                $container->get(Logger::class)
            );

            $response = $app->getResponseFactory()->createResponse()->withStatus(500);
            $response = (new CorsResponseManager())->withCors($request, $response);

            return $errorHandler->handleError($exception, $response, $request, $displayErrorDetails);
        };

        $errorMiddleware = $app->addErrorMiddleware(true, true, true);
        $errorMiddleware->setDefaultErrorHandler($customErrorHandler);
        $errorMiddleware->setErrorHandler(Throwable::class, $customErrorHandler);
    }

    public function registerNotFoundRoutes(App $app): void
    {
        $app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', [$this, 'get404Response']);
    }

    public function get404Response(ResponseInterface $response): ResponseInterface
    {
        $content404 = json_encode([
            'error' => '404 Not Found',
        ], JSON_THROW_ON_ERROR);

        $response->getBody()->write($content404);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    public function registerPreflight(App $app): void
    {
        $callback = function (ResponseInterface $response) {
            return $response;
        };

        $app->map(['OPTIONS'], '/{routes:.+}', $callback);
    }

    public function registerApiRoutes(App $app): void
    {
        $app->group('/api/v1', function (RouteCollectorProxy $app) {
            $app->group('/linkCollection', function (RouteCollectorProxy $linkCollection) {
                $linkCollection->post('/create', CreateLinkAction::class);
            });
        });
    }
}
