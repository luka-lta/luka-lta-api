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
use LukaLtaApi\Service\AvatarService;
use LukaLtaApi\Service\ChannelDetectorService;
use LukaLtaApi\Service\Contracts\AvatarServiceInterface;
use LukaLtaApi\Service\Contracts\ChannelDetectorServiceInterface;
use LukaLtaApi\Service\Contracts\CryptServiceInterface;
use LukaLtaApi\Service\Contracts\LinkItemCachingServiceInterface;
use LukaLtaApi\Service\Contracts\PaginationServiceInterface;
use LukaLtaApi\Service\Contracts\PermissionServiceInterface;
use LukaLtaApi\Service\Contracts\PreviewTokenValidationServiceInterface;
use LukaLtaApi\Service\Contracts\TokenServiceInterface;
use LukaLtaApi\Service\Contracts\UserValidationServiceInterface;
use LukaLtaApi\Service\CryptService;
use LukaLtaApi\Service\LinkItemCachingService;
use LukaLtaApi\Service\PaginationService;
use LukaLtaApi\Service\PermissionService;
use LukaLtaApi\Service\PreviewTokenValidationService;
use LukaLtaApi\Service\TokenService;
use LukaLtaApi\Service\UserValidationService;
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
            AvatarServiceInterface::class => autowire(AvatarService::class),
            ChannelDetectorServiceInterface::class => autowire(ChannelDetectorService::class),
            CryptServiceInterface::class => autowire(CryptService::class),
            LinkItemCachingServiceInterface::class => autowire(LinkItemCachingService::class),
            PaginationServiceInterface::class => autowire(PaginationService::class),
            PermissionServiceInterface::class => autowire(PermissionService::class),
            PreviewTokenValidationServiceInterface::class => autowire(PreviewTokenValidationService::class),
            TokenServiceInterface::class => autowire(TokenService::class),
            UserValidationServiceInterface::class => autowire(UserValidationService::class),
        ];
    }
}
