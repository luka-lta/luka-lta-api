<?php

namespace LukaLtaApi\Slim;

use LukaLtaApi\Api\ApiKey\Create\CreateApiKeyAction;
use LukaLtaApi\Api\Auth\AuthAction;
use LukaLtaApi\Api\Click\GetAll\GetAllClicksAction;
use LukaLtaApi\Api\Click\Track\ClickTrackAction;
use LukaLtaApi\Api\Health\GetHealthAction;
use LukaLtaApi\Api\LinkCollection\Create\CreateLinkAction;
use LukaLtaApi\Api\LinkCollection\Disable\DisableLinkAction;
use LukaLtaApi\Api\LinkCollection\Edit\EditLinkAction;
use LukaLtaApi\Api\LinkCollection\GetAll\GetAllLinksAction;
use LukaLtaApi\Api\User\Create\CreateUserAction;
use LukaLtaApi\Api\User\GetAll\GetAllUsersAction;
use LukaLtaApi\Api\User\Update\UpdateUserAction;
use LukaLtaApi\Slim\Middleware\AuthMiddleware;
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
            $app->post('/auth', AuthAction::class);
            $app->get('/health', GetHealthAction::class);

            $app->group('/key', function (RouteCollectorProxy $key) {
                $key->post('/create', CreateApiKeyAction::class);
            })->add(AuthMiddleware::class);

            $app->group('/linkCollection', function (RouteCollectorProxy $linkCollection) {
                $linkCollection->post('/create', CreateLinkAction::class);
                $linkCollection->get('/links', GetAllLinksAction::class);
                $linkCollection->put('/link/{linkId:[0-9]+}', EditLinkAction::class);
                $linkCollection->delete('/link/{linkId:[0-9]+}', DisableLinkAction::class);
            })->add(AuthMiddleware::class);

            $app->group('/click', function (RouteCollectorProxy $click) {
                $click->get('/track', ClickTrackAction::class);
                $click->get('/all', GetAllClicksAction::class)->add(AuthMiddleware::class);
            });

            $app->group('/user', function (RouteCollectorProxy $user) {
                $user->post('/create', CreateUserAction::class);
                $user->post('/{userId:[0-9]+}', UpdateUserAction::class);
                $user->get('/all', GetAllUsersAction::class);
            })->add(AuthMiddleware::class);
        });
    }
}
