<?php

namespace LukaLtaApi;

use Aws\S3\S3Client;
use ClickHouseDB\Client;
use DI\Definition\Source\DefinitionArray;
use LukaLtaApi\App\Factory\AppEnvFactory;
use LukaLtaApi\App\Factory\ClickHouseFactory;
use LukaLtaApi\App\Factory\LoggerFactory;
use LukaLtaApi\App\Factory\MinIOFactory;
use LukaLtaApi\App\Factory\PdoFactory;
use LukaLtaApi\App\Factory\RedisFactory;
use LukaLtaApi\App\Factory\TelegramBotFactory;
use LukaLtaApi\Repository\ApiKeyRepository;
use LukaLtaApi\Repository\ClickRepository;
use LukaLtaApi\Repository\Contracts\ApiKeyRepositoryInterface;
use LukaLtaApi\Repository\Contracts\ClickRepositoryInterface;
use LukaLtaApi\Repository\Contracts\EnvironmentRepositoryInterface;
use LukaLtaApi\Repository\Contracts\GeoLocationRepositoryInterface;
use LukaLtaApi\Repository\Contracts\LinkCollectionRepositoryInterface;
use LukaLtaApi\Repository\Contracts\PaginationRepositoryInterface;
use LukaLtaApi\Repository\Contracts\PermissionRepositoryInterface;
use LukaLtaApi\Repository\Contracts\PreviewTokenRepositoryInterface;
use LukaLtaApi\Repository\Contracts\S3RepositoryInterface;
use LukaLtaApi\Repository\Contracts\SessionRepositoryInterface;
use LukaLtaApi\Repository\Contracts\SiteMetricRepositoryInterface;
use LukaLtaApi\Repository\Contracts\SiteRepositoryInterface;
use LukaLtaApi\Repository\Contracts\StatisticRepositoryInterface;
use LukaLtaApi\Repository\Contracts\TrackingUserAliasRepositoryInterface;
use LukaLtaApi\Repository\Contracts\TrackingUserRepositoryInterface;
use LukaLtaApi\Repository\Contracts\UserRepositoryInterface;
use LukaLtaApi\Repository\EnvironmentRepository;
use LukaLtaApi\Repository\GeoLocationRepository;
use LukaLtaApi\Repository\LinkCollectionRepository;
use LukaLtaApi\Repository\PaginationRepository;
use LukaLtaApi\Repository\PermissionRepository;
use LukaLtaApi\Repository\PreviewTokenRepository;
use LukaLtaApi\Repository\S3Repository;
use LukaLtaApi\Repository\SessionRepository;
use LukaLtaApi\Repository\SiteMetricRepository;
use LukaLtaApi\Repository\SiteRepository;
use LukaLtaApi\Repository\StatisticRepository;
use LukaLtaApi\Repository\TrackingUserAliasRepository;
use LukaLtaApi\Repository\TrackingUserRepository;
use LukaLtaApi\Repository\UserRepository;
use LukaLtaApi\Value\Misc\AppEnv;
use PDO;
use Psr\Log\LoggerInterface;
use Redis;
use TelegramBot\Api\BotApi;

use function DI\autowire;
use function DI\factory;

class ApplicationConfig extends DefinitionArray
{
    public function __construct()
    {
        parent::__construct($this->getConfig());
    }

    private function getConfig(): array
    {
        return [
            PDO::class => factory(PdoFactory::class),
            Redis::class => factory(RedisFactory::class),
            BotApi::class => factory(TelegramBotFactory::class),
            S3Client::class => factory(MinIOFactory::class),
            Client::class => factory(ClickHouseFactory::class),
            ApiKeyRepositoryInterface::class => autowire(ApiKeyRepository::class),
            ClickRepositoryInterface::class => autowire(ClickRepository::class),
            EnvironmentRepositoryInterface::class => autowire(EnvironmentRepository::class),
            GeoLocationRepositoryInterface::class => autowire(GeoLocationRepository::class),
            LinkCollectionRepositoryInterface::class => autowire(LinkCollectionRepository::class),
            PaginationRepositoryInterface::class => autowire(PaginationRepository::class),
            PermissionRepositoryInterface::class => autowire(PermissionRepository::class),
            PreviewTokenRepositoryInterface::class => autowire(PreviewTokenRepository::class),
            S3RepositoryInterface::class => autowire(S3Repository::class),
            SessionRepositoryInterface::class => autowire(SessionRepository::class),
            SiteMetricRepositoryInterface::class => autowire(SiteMetricRepository::class),
            SiteRepositoryInterface::class => autowire(SiteRepository::class),
            StatisticRepositoryInterface::class => autowire(StatisticRepository::class),
            TrackingUserAliasRepositoryInterface::class => autowire(TrackingUserAliasRepository::class),
            TrackingUserRepositoryInterface::class => autowire(TrackingUserRepository::class),
            UserRepositoryInterface::class => autowire(UserRepository::class),
        ];
    }
}
