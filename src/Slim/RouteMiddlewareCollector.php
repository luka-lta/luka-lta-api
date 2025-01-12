<?php

namespace LukaLtaApi\Slim;

use LukaLtaApi\Api\ApiKey\Create\CreateApiKeyAction;
use LukaLtaApi\Api\ApiKey\GetAll\GetAllApiKeysAction;
use LukaLtaApi\Api\Auth\AuthAction;
use LukaLtaApi\Api\Click\GetAll\GetAllClicksAction;
use LukaLtaApi\Api\Click\Track\ClickTrackAction;
use LukaLtaApi\Api\Health\GetHealthAction;
use LukaLtaApi\Api\LinkCollection\Create\CreateLinkAction;
use LukaLtaApi\Api\LinkCollection\Disable\DisableLinkAction;
use LukaLtaApi\Api\LinkCollection\Edit\EditLinkAction;
use LukaLtaApi\Api\LinkCollection\GetAll\GetAllLinksAction;
use LukaLtaApi\Api\Todo\Create\CreateTodoAction;
use LukaLtaApi\Api\Todo\Delete\DeleteTodoAction;
use LukaLtaApi\Api\Todo\GetAll\GetAllTodoAction;
use LukaLtaApi\Api\Todo\Update\UpdateTodoAction;
use LukaLtaApi\Api\User\Avatar\GetAvatarAction;
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
            $app->get('/avatar/{filename:[a-zA-Z0-9_-]+\.(jpg|png|jpeg)}', GetAvatarAction::class);

            $app->group('/key', function (RouteCollectorProxy $key) {
                $key->post('/', CreateApiKeyAction::class);
                $key->get('/', GetAllApiKeysAction::class);
            })->add(AuthMiddleware::class);

            $app->group('/todo', function (RouteCollectorProxy $todo) {
                $todo->post('/', CreateTodoAction::class);
                $todo->put('/{todoId:[0-9]+}', UpdateTodoAction::class);
                $todo->get('/', GetAllTodoAction::class);
                $todo->delete('/{todoId:[0-9]+}', DeleteTodoAction::class);
            })->add(AuthMiddleware::class);

            $app->group('/linkCollection', function (RouteCollectorProxy $linkCollection) {
                $linkCollection->post('/', CreateLinkAction::class);
                $linkCollection->get('/', GetAllLinksAction::class);
                $linkCollection->put('/{linkId:[0-9]+}', EditLinkAction::class);
                $linkCollection->delete('/{linkId:[0-9]+}', DisableLinkAction::class);
            })->add(AuthMiddleware::class);

            $app->group('/click', function (RouteCollectorProxy $click) {
                $click->get('/track', ClickTrackAction::class);
                $click->get('/', GetAllClicksAction::class)->add(AuthMiddleware::class);
            });

            $app->group('/user', function (RouteCollectorProxy $user) {
                $user->post('/', CreateUserAction::class);
                $user->put('/{userId:[0-9]+}', UpdateUserAction::class);
                $user->get('/', GetAllUsersAction::class);
            })->add(AuthMiddleware::class);
        });
    }
}
