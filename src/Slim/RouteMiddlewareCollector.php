<?php

namespace LukaLtaApi\Slim;

use LukaLtaApi\Api\Blog\Action\CreateBlogAction;
use LukaLtaApi\Api\Blog\Action\CreateTagAction;
use LukaLtaApi\Api\Blog\Action\DeleteBlogAction;
use LukaLtaApi\Api\Blog\Action\DeleteTagAction;
use LukaLtaApi\Api\Blog\Action\GetAllBlogsAction;
use LukaLtaApi\Api\Blog\Action\GetBlogAction;
use LukaLtaApi\Api\Blog\Action\GetTagsAction;
use LukaLtaApi\Api\Blog\Action\PublishBlogAction;
use LukaLtaApi\Api\Blog\Action\UpdateBlogAction;
use LukaLtaApi\Api\Auth\Action\AuthAction;
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
use LukaLtaApi\Api\LinkCollection\Action\GetDetailLinkAction;
use LukaLtaApi\Api\SelfUser\Action\GetSelfUserAction;
use LukaLtaApi\Api\SelfUser\Action\SelfUserUpdateAction;
use LukaLtaApi\Api\Statistics\Action\GetStatisticsAction;
use LukaLtaApi\Api\User\Action\CreateUserAction;
use LukaLtaApi\Api\User\Action\DeactivateUserAction;
use LukaLtaApi\Api\User\Action\DeleteUserAction;
use LukaLtaApi\Api\User\Action\GetAllUsersAction;
use LukaLtaApi\Api\User\Action\GetAvatarAction;
use LukaLtaApi\Api\User\Action\UpdateProfileAction;
use LukaLtaApi\Slim\Middleware\AuthMiddleware;
use LukaLtaApi\Slim\Middleware\CORSMiddleware;
use LukaLtaApi\Value\Misc\AppEnv;
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
                $container->get(LoggerInterface::class),
                $container->get(AppEnv::class),
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
            $app->post('/auth/login', AuthAction::class);
            $app->get('/health', GetHealthAction::class);
            $app->get('/avatar/{userId}', GetAvatarAction::class);

            $app->group('/linkCollection', function (RouteCollectorProxy $linkCollection) use ($app) {
                $linkCollection->post('/', CreateLinkAction::class);
                $linkCollection->get('/', GetAllLinksAction::class);
                $linkCollection->get('/{linkId:[0-9]+}', GetDetailLinkAction::class);
                $linkCollection->put('/{linkId:[0-9]+}', EditLinkAction::class);
                $linkCollection->delete('/{linkId:[0-9]+}', DisableLinkAction::class);
            })->add(AuthMiddleware::class);

            $app->group('/click', function (RouteCollectorProxy $click) use ($app) {
                $click->post('/track/{clickTag}', ClickTrackAction::class);
                $click->get('/stats', GetClicksStatsAction::class)
                    ->add(AuthMiddleware::class);
                $click->get('/filters', GetClicksFiltersAction::class)
                    ->add(AuthMiddleware::class);
                $click->get('/', GetClicksAction::class)
                    ->add(AuthMiddleware::class);

                $click->get('/summary/', GetClickSummaryAction::class)
                    ->add(AuthMiddleware::class);
            });

            $app->group('/user', function (RouteCollectorProxy $user) {
                $user->post('/', CreateUserAction::class);
                $user->post('/{userId:[0-9]+}', UpdateProfileAction::class);
                $user->get('/', GetAllUsersAction::class);
                $user->put('/deactivate/{userId:[0-9]+}', DeactivateUserAction::class);
                $user->delete('/{userId:[0-9]+}', DeleteUserAction::class);
            })->add(AuthMiddleware::class);

            $app->group('/self', function (RouteCollectorProxy $selfUser) use ($app) {
                $selfUser->get('/', GetSelfUserAction::class);
                $selfUser->put('/', SelfUserUpdateAction::class);
            })->add(AuthMiddleware::class);

            $app->group('/statistics', function (RouteCollectorProxy $statistics) use ($app) {
                $statistics->get('/', GetStatisticsAction::class);
            })->add(AuthMiddleware::class);

            // Blog — public read routes
            $app->get('/blog', GetAllBlogsAction::class);
            $app->get('/blog/tags', GetTagsAction::class);
            $app->get('/blog/{blogId}', GetBlogAction::class);

            // Blog — protected write routes
            $app->group('/blog', function (RouteCollectorProxy $blog) {
                $blog->post('', CreateBlogAction::class);
                $blog->put('/{blogId}', UpdateBlogAction::class);
                $blog->delete('/{blogId}', DeleteBlogAction::class);
                $blog->patch('/{blogId}/publish', PublishBlogAction::class);
                $blog->post('/tags', CreateTagAction::class);
                $blog->delete('/tags/{tagId:[0-9]+}', DeleteTagAction::class);
            })->add(AuthMiddleware::class);
        });
    }
}
