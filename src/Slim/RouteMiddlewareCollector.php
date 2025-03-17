<?php

namespace LukaLtaApi\Slim;

use LukaLtaApi\Api\ApiKey\Action\CreateApiKeyAction;
use LukaLtaApi\Api\ApiKey\Action\GetAllApiKeysAction;
use LukaLtaApi\Api\Auth\Action\AuthAction;
use LukaLtaApi\Api\Click\GetAll\GetAllClicksAction;
use LukaLtaApi\Api\Click\Track\ClickTrackAction;
use LukaLtaApi\Api\Health\Action\GetHealthAction;
use LukaLtaApi\Api\LinkCollection\Action\CreateLinkAction;
use LukaLtaApi\Api\LinkCollection\Action\DisableLinkAction;
use LukaLtaApi\Api\LinkCollection\Action\EditLinkAction;
use LukaLtaApi\Api\LinkCollection\Action\GetAllLinksAction;
use LukaLtaApi\Api\LinkCollection\Action\GetDetailLink;
use LukaLtaApi\Api\Permission\Action\GetPermissionsAction;
use LukaLtaApi\Api\Todo\Action\CreateTodoAction;
use LukaLtaApi\Api\Todo\Action\DeleteTodoAction;
use LukaLtaApi\Api\Todo\Action\GetAllTodoAction;
use LukaLtaApi\Api\Todo\Action\UpdateTodoAction;
use LukaLtaApi\Api\User\Action\CreateUserAction;
use LukaLtaApi\Api\User\Action\GetAllUsersAction;
use LukaLtaApi\Api\User\Action\GetAvatarAction;
use LukaLtaApi\Api\User\Action\UpdateUserAction;
use LukaLtaApi\Service\PermissionService;
use LukaLtaApi\Slim\Middleware\ApiKeyPermissionMiddleware;
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
            $app->get('/avatar/{filename}', GetAvatarAction::class);

            $app->group('/key', function (RouteCollectorProxy $key) use ($app) {
                $key->post('/', CreateApiKeyAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Create API keys']
                    ));
                $key->get('/', GetAllApiKeysAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Read API keys']
                    ));
            })->add(AuthMiddleware::class);

            $app->group('/todo', function (RouteCollectorProxy $todo) use ($app) {
                $todo->post('/', CreateTodoAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Create todos']
                    ));
                $todo->put('/{todoId:[0-9]+}', UpdateTodoAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Edit todos']
                    ));
                $todo->get('/', GetAllTodoAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Read todos']
                    ));
                $todo->delete('/{todoId:[0-9]+}', DeleteTodoAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Delete todos']
                    ));
            })->add(AuthMiddleware::class);

            $app->group('/linkCollection', function (RouteCollectorProxy $linkCollection) use ($app) {
                $linkCollection->post('/', CreateLinkAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Create links']
                    ));
                $linkCollection->get('/', GetAllLinksAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Read links']
                    ));
                $linkCollection->get('/{linkId:[0-9]+}', GetDetailLink::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Read links']
                    ));
                $linkCollection->put('/{linkId:[0-9]+}', EditLinkAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Edit links']
                    ));
                $linkCollection->delete('/{linkId:[0-9]+}', DisableLinkAction::class);
            })->add(AuthMiddleware::class);

            $app->group('/click', function (RouteCollectorProxy $click) use ($app) {
                $click->get('/track', ClickTrackAction::class);
                $click->get('/', GetAllClicksAction::class)
                    ->add(AuthMiddleware::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Get clicks']
                    ));
            });

            $app->group('/user', function (RouteCollectorProxy $user) {
                $user->post('/', CreateUserAction::class);
                $user->put('/{userId:[0-9]+}', UpdateUserAction::class);
                $user->get('/', GetAllUsersAction::class);
            })->add(AuthMiddleware::class);

            $app->group('/permissions', function (RouteCollectorProxy $permissions) use ($app) {
                $permissions->get('/', GetPermissionsAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Read permissions']
                    ));
            })->add(AuthMiddleware::class);
        });
    }
}
