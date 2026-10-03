<?php

namespace LukaLtaApi\Slim;

use LukaLtaApi\Api\ApiKey\Action\CreateApiKeyAction;
use LukaLtaApi\Api\ApiKey\Action\DeleteApiKeyAction;
use LukaLtaApi\Api\ApiKey\Action\ListApiKeysAction;
use LukaLtaApi\Api\ApiKey\Action\ListPermissionsAction;
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
use LukaLtaApi\Api\Calendar\Action\CreateCalendarSourceAction;
use LukaLtaApi\Api\Calendar\Action\DeleteCalendarSourceAction;
use LukaLtaApi\Api\Calendar\Action\GetCalendarEventsAction;
use LukaLtaApi\Api\Calendar\Action\GetCalendarSummaryAction;
use LukaLtaApi\Api\Calendar\Action\ListCalendarSourcesAction;
use LukaLtaApi\Api\Calendar\Action\UpdateCalendarSourceAction;
use LukaLtaApi\Api\Homelab\Action\CreateTopologyEdgeAction;
use LukaLtaApi\Api\Homelab\Action\CreateTopologyNodeAction;
use LukaLtaApi\Api\Homelab\Action\DeleteTopologyEdgeAction;
use LukaLtaApi\Api\Homelab\Action\DeleteTopologyNodeAction;
use LukaLtaApi\Api\Homelab\Action\GetContainerAction;
use LukaLtaApi\Api\Homelab\Action\GetContainerMetricsAction;
use LukaLtaApi\Api\Homelab\Action\GetHostMetricsAction;
use LukaLtaApi\Api\Homelab\Action\GetTopologyAction;
use LukaLtaApi\Api\Homelab\Action\IngestContainerMetricsAction;
use LukaLtaApi\Api\Homelab\Action\IngestHostMetricsAction;
use LukaLtaApi\Api\Homelab\Action\ListAlertsAction;
use LukaLtaApi\Api\Homelab\Action\ListContainersAction;
use LukaLtaApi\Api\Homelab\Action\ListEventsAction;
use LukaLtaApi\Api\Homelab\Action\ListHostsAction;
use LukaLtaApi\Api\Homelab\Action\UpdateContainerRoleAction;
use LukaLtaApi\Api\Homelab\Action\UpdateHostNodeTypeAction;
use LukaLtaApi\Api\Weather\Action\GetWeatherDetailAction;
use LukaLtaApi\Api\Weather\Action\GetWeatherLocationAction;
use LukaLtaApi\Api\Weather\Action\GetWeatherSummaryAction;
use LukaLtaApi\Api\Weather\Action\UpdateWeatherLocationAction;
use LukaLtaApi\Api\LinkCollection\Action\ActivateLinkAction;
use LukaLtaApi\Api\LinkCollection\Action\CreateLinkAction;
use LukaLtaApi\Api\LinkCollection\Action\DeactivateLinkAction;
use LukaLtaApi\Api\LinkCollection\Action\DeleteLinkAction;
use LukaLtaApi\Api\LinkCollection\Action\EditLinkAction;
use LukaLtaApi\Api\LinkCollection\Action\GetAllLinksAction;
use LukaLtaApi\Api\LinkCollection\Action\GetDetailLinkAction;
use LukaLtaApi\Api\Notification\Action\ListNotificationsAction;
use LukaLtaApi\Api\Notification\Action\MarkAllNotificationsReadAction;
use LukaLtaApi\Api\Notification\Action\MarkNotificationReadAction;
use LukaLtaApi\Api\Project\Action\CreateProjectAction;
use LukaLtaApi\Api\Project\Action\CreateProjectTagAction;
use LukaLtaApi\Api\Project\Action\DeleteProjectAction;
use LukaLtaApi\Api\Project\Action\GetAllProjectsAction;
use LukaLtaApi\Api\Project\Action\GetManagedProjectAction;
use LukaLtaApi\Api\Project\Action\GetManagedProjectsAction;
use LukaLtaApi\Api\Project\Action\GetProjectAction;
use LukaLtaApi\Api\Project\Action\GetProjectTagsAction;
use LukaLtaApi\Api\Project\Action\ReorderProjectsAction;
use LukaLtaApi\Api\Project\Action\UpdateProjectAction;
use LukaLtaApi\Api\SelfUser\Action\GetSelfUserAction;
use LukaLtaApi\Api\SelfUser\Action\SelfUserUpdateAction;
use LukaLtaApi\Api\Statistics\Action\GetStatisticsAction;
use LukaLtaApi\Api\User\Action\CreateUserAction;
use LukaLtaApi\Api\User\Action\DeactivateUserAction;
use LukaLtaApi\Api\User\Action\DeleteUserAction;
use LukaLtaApi\Api\User\Action\GetAllUsersAction;
use LukaLtaApi\Api\User\Action\GetAvatarAction;
use LukaLtaApi\Api\User\Action\UpdateProfileAction;
use LukaLtaApi\Repository\ApiKeyRepository;
use LukaLtaApi\Slim\Middleware\ApiKeyPermissionMiddleware;
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
        $container = $app->getContainer();

        $app->group('/api/v1', function (RouteCollectorProxy $app) use ($container) {
            $app->post('/auth/login', AuthAction::class);
            $app->get('/health', GetHealthAction::class);
            $app->get('/avatar/{userId}', GetAvatarAction::class);

            $app->get('/linkCollection', GetAllLinksAction::class);
            $app->get('/linkCollection/', GetAllLinksAction::class);
            $app->get('/linkCollection/{linkId:[0-9]+}', GetDetailLinkAction::class);

            $app->group('/linkCollection', function (RouteCollectorProxy $linkCollection) use ($app) {
                $linkCollection->post('/', CreateLinkAction::class);
                $linkCollection->put('/{linkId:[0-9]+}', EditLinkAction::class);
                $linkCollection->put('/deactivate/{linkId:[0-9]+}', DeactivateLinkAction::class);
                $linkCollection->put('/activate/{linkId:[0-9]+}', ActivateLinkAction::class);
                $linkCollection->delete('/{linkId:[0-9]+}', DeleteLinkAction::class);
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

            // Homelab — dashboard read routes
            $app->group('/homelab', function (RouteCollectorProxy $homelab) {
                $homelab->get('/hosts', ListHostsAction::class);
                $homelab->get('/hosts/{hostId}/metrics', GetHostMetricsAction::class);
                $homelab->get('/containers', ListContainersAction::class);
                $homelab->get('/containers/{containerId}', GetContainerAction::class);
                $homelab->get('/containers/{containerId}/metrics', GetContainerMetricsAction::class);
                $homelab->get('/alerts', ListAlertsAction::class);
                $homelab->get('/events', ListEventsAction::class);
                $homelab->patch('/hosts/{hostId}', UpdateHostNodeTypeAction::class);
                $homelab->patch('/containers/{containerId}/role', UpdateContainerRoleAction::class);

                $homelab->get('/topology', GetTopologyAction::class);
                $homelab->post('/topology/nodes', CreateTopologyNodeAction::class);
                $homelab->delete('/topology/nodes/{nodeId}', DeleteTopologyNodeAction::class);
                $homelab->post('/topology/edges', CreateTopologyEdgeAction::class);
                $homelab->delete('/topology/edges/{edgeId}', DeleteTopologyEdgeAction::class);
            })->add(AuthMiddleware::class);

            // Weather — dashboard widget routes
            $app->group('/weather', function (RouteCollectorProxy $weather) {
                $weather->get('', GetWeatherSummaryAction::class);
                $weather->get('/detail', GetWeatherDetailAction::class);
                $weather->get('/location', GetWeatherLocationAction::class);
                $weather->patch('/location', UpdateWeatherLocationAction::class);
            })->add(AuthMiddleware::class);

            // Calendar — dashboard widget routes
            $app->group('/calendar', function (RouteCollectorProxy $calendar) {
                $calendar->get('', GetCalendarSummaryAction::class);
                $calendar->get('/events', GetCalendarEventsAction::class);
                $calendar->get('/sources', ListCalendarSourcesAction::class);
                $calendar->post('/sources', CreateCalendarSourceAction::class);
                $calendar->patch('/sources/{sourceId}', UpdateCalendarSourceAction::class);
                $calendar->delete('/sources/{sourceId}', DeleteCalendarSourceAction::class);
            })->add(AuthMiddleware::class);

            $app->group('/notifications', function (RouteCollectorProxy $notifications) {
                $notifications->get('', ListNotificationsAction::class);
                $notifications->patch('/read-all', MarkAllNotificationsReadAction::class);
                $notifications->patch('/{alertId}/read', MarkNotificationReadAction::class);
            })->add(AuthMiddleware::class);

            // Homelab — agent ingest routes, API-key authenticated
            $app->group('/homelab/ingest', function (RouteCollectorProxy $ingest) {
                $ingest->post('/host', IngestHostMetricsAction::class);
                $ingest->post('/container', IngestContainerMetricsAction::class);
            })->add(new ApiKeyPermissionMiddleware(
                $container->get(ApiKeyRepository::class),
                'Ingest Homelab Metrics',
            ));

            // Projects — Tag-Dictionary, nur Dashboard
            $app->group('/projects/tags', function (RouteCollectorProxy $tags) {
                $tags->get('', GetProjectTagsAction::class);
                $tags->post('', CreateProjectTagAction::class);
            })->add(AuthMiddleware::class);

            // Projects — geschuetzte Verwaltung (Dashboard)
            $app->group('/projects', function (RouteCollectorProxy $projects) {
                $projects->get('/manage', GetManagedProjectsAction::class);
                $projects->get('/manage/{projectId}', GetManagedProjectAction::class);
                $projects->post('', CreateProjectAction::class);
                $projects->patch('/order', ReorderProjectsAction::class);
                $projects->patch('/{projectId}', UpdateProjectAction::class);
                $projects->delete('/{projectId}', DeleteProjectAction::class);
            })->add(AuthMiddleware::class);

            // Projects — public read routes
            $app->get('/projects', GetAllProjectsAction::class);
            $app->get('/projects/{slug}', GetProjectAction::class);

            // API key management — dashboard only
            $app->group('/api-keys', function (RouteCollectorProxy $apiKeys) {
                $apiKeys->post('/', CreateApiKeyAction::class);
                $apiKeys->get('/', ListApiKeysAction::class);
                $apiKeys->delete('/{keyId:[0-9]+}', DeleteApiKeyAction::class);
            })->add(AuthMiddleware::class);

            $app->group('/permissions', function (RouteCollectorProxy $permissions) {
                $permissions->get('/', ListPermissionsAction::class);
            })->add(AuthMiddleware::class);
        });
    }
}
