<?php

namespace LukaLtaApi\Slim;

use LukaLtaApi\Api\ApiKey\Action\CreateApiKeyAction;
use LukaLtaApi\Api\ApiKey\Action\GetAllApiKeysAction;
use LukaLtaApi\Api\Auth\Action\AuthAction;
use LukaLtaApi\Api\Blog\Action\BlogCreateAction;
use LukaLtaApi\Api\Blog\Action\BlogUpdateAction;
use LukaLtaApi\Api\Blog\Action\GetBlogByIdAction;
use LukaLtaApi\Api\Blog\Action\GetBlogsAction;
use LukaLtaApi\Api\Click\Action\ClickTrackAction;
use LukaLtaApi\Api\Click\Action\GetClicksAction;
use LukaLtaApi\Api\Click\Action\GetClicksFiltersAction;
use LukaLtaApi\Api\Click\Action\GetClicksStatsAction;
use LukaLtaApi\Api\Click\Action\GetClickSummaryAction;
use LukaLtaApi\Api\Health\Action\GetHealthAction;
use LukaLtaApi\Api\LinkCollection\Action\CreateLinkAction;
use LukaLtaApi\Api\LinkCollection\Action\DisableLinkAction;
use LukaLtaApi\Api\LinkCollection\Action\EditLinkAction;
use LukaLtaApi\Api\LinkCollection\Action\GetAllLinksAction;
use LukaLtaApi\Api\LinkCollection\Action\GetDetailLink;
use LukaLtaApi\Api\Permission\Action\GetPermissionsAction;
use LukaLtaApi\Api\PreviewToken\Action\CreatePreviewTokenAction;
use LukaLtaApi\Api\PreviewToken\Action\DeletePreviewTokenAction;
use LukaLtaApi\Api\PreviewToken\Action\ListPreviewTokensAction;
use LukaLtaApi\Api\PreviewToken\Action\UpdatePreviewTokenAction;
use LukaLtaApi\Api\Register\Action\RegisterUserAction;
use LukaLtaApi\Api\SelfUser\Action\GetSelfUserAction;
use LukaLtaApi\Api\SelfUser\Action\SelfUserUpdateAction;
use LukaLtaApi\Api\Statistics\Action\GetStatisticsAction;
use LukaLtaApi\Api\User\Action\CreateUserAction;
use LukaLtaApi\Api\User\Action\DeactivateUserAction;
use LukaLtaApi\Api\User\Action\DeleteUserAction;
use LukaLtaApi\Api\User\Action\GetAllUsersAction;
use LukaLtaApi\Api\User\Action\GetAvatarAction;
use LukaLtaApi\Api\User\Action\UpdateProfileAction;
use LukaLtaApi\Service\PermissionService;
use LukaLtaApi\Slim\Middleware\ApiKeyPermissionMiddleware;
use LukaLtaApi\Slim\Middleware\AuthMiddleware;
use LukaLtaApi\Slim\Middleware\CORSMiddleware;
use LukaLtaApi\Value\Permission\Permission;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
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
                $container->get(LoggerInterface::class)
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
            $app->post('/register', RegisterUserAction::class);
            $app->get('/health', GetHealthAction::class);
            $app->get('/avatar/{filename}', GetAvatarAction::class);

            $app->group('/key', function (RouteCollectorProxy $key) use ($app) {
                $key->post('/', CreateApiKeyAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        [Permission::CREATE_API_KEYS]
                    ));
                $key->get('/', GetAllApiKeysAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        [Permission::READ_API_KEYS]
                    ));
            })->add(AuthMiddleware::class);

            $app->group('/linkCollection', function (RouteCollectorProxy $linkCollection) use ($app) {
                $linkCollection->post('/', CreateLinkAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        [Permission::CREATE_LINKS]
                    ));
                $linkCollection->get('/', GetAllLinksAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        [Permission::VIEW_LINKS]
                    ));
                $linkCollection->get('/{linkId:[0-9]+}', GetDetailLink::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        [Permission::VIEW_LINKS]
                    ));
                $linkCollection->put('/{linkId:[0-9]+}', EditLinkAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        [Permission::EDIT_LINKS]
                    ));
                $linkCollection->delete('/{linkId:[0-9]+}', DisableLinkAction::class);
            })->add(AuthMiddleware::class);

            $app->group('/click', function (RouteCollectorProxy $click) use ($app) {
                $click->post('/track/{clickTag}', ClickTrackAction::class);
                $click->get('/stats', GetClicksStatsAction::class)
                    ->add(AuthMiddleware::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        [Permission::VIEW_CLICKS]
                    ));
                $click->get('/filters', GetClicksFiltersAction::class)
                    ->add(AuthMiddleware::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        [Permission::VIEW_CLICKS]
                    ));
                $click->get('/', GetClicksAction::class)
                    ->add(AuthMiddleware::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        [Permission::VIEW_CLICKS]
                    ));

                $click->get('/summary/', GetClickSummaryAction::class)
                    ->add(AuthMiddleware::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        [Permission::VIEW_CLICKS]
                    ));
            });

            $app->group('/user', function (RouteCollectorProxy $user) {
                $user->post('/', CreateUserAction::class);
                $user->put('/{userId:[0-9]+}', UpdateProfileAction::class);
                $user->get('/', GetAllUsersAction::class);
                $user->put('/deactivate/{userId:[0-9]+}', DeactivateUserAction::class);
                $user->delete('/{userId:[0-9]+}', DeleteUserAction::class);
            })->add(AuthMiddleware::class);

            $app->group('/permissions', function (RouteCollectorProxy $permissions) use ($app) {
                $permissions->get('/', GetPermissionsAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        [Permission::READ_PERMISSIONS]
                    ));
            })->add(AuthMiddleware::class);

            $app->group('/previewToken', function (RouteCollectorProxy $previewToken) use ($app) {
                $previewToken->post('/', CreatePreviewTokenAction::class)->add(
                    new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Create preview tokens']
                    )
                );

                $previewToken->get('/', ListPreviewTokensAction::class)->add(
                    new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Read preview tokens']
                    )
                );

                $previewToken->put('/{previewTokenId:[0-9]+}', UpdatePreviewTokenAction::class)->add(
                    new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Edit preview tokens']
                    )
                );

                $previewToken->delete('/{previewTokenId:[0-9]+}', DeletePreviewTokenAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Delete preview tokens']
                    ));
            })->add(AuthMiddleware::class);

            $app->group('/blog', function (RouteCollectorProxy $blog) use ($app) {
                $blog->post('/', BlogCreateAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Create blog posts']
                    ));

                $blog->put(
                    '/{blogId:[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-4[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}}',
                    BlogUpdateAction::class
                )->add(new ApiKeyPermissionMiddleware(
                    $app->getContainer()?->get(PermissionService::class),
                    ['Edit blog posts']
                ));

                $blog->get(
                    '/{blogId:[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-4[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}}',
                    GetBlogByIdAction::class
                )->add(new ApiKeyPermissionMiddleware(
                    $app->getContainer()?->get(PermissionService::class),
                    ['Read blog post by ID']
                ));

                $blog->get('/', GetBlogsAction::class)
                    ->add(new ApiKeyPermissionMiddleware(
                        $app->getContainer()?->get(PermissionService::class),
                        ['Read blog posts']
                    ));
            })->add(AuthMiddleware::class);

            $app->group('/self', function (RouteCollectorProxy $selfUser) use ($app) {
                $selfUser->get('/', GetSelfUserAction::class);
                $selfUser->put('/', SelfUserUpdateAction::class);
            })->add(AuthMiddleware::class);

            $app->group('/statistics', function (RouteCollectorProxy $statistics) use ($app) {
                $statistics->get('/', GetStatisticsAction::class);
            })->add(AuthMiddleware::class);
        });
    }
}
