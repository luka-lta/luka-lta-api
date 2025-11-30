<?php

namespace LukaLtaApi;

use Aws\S3\S3Client;
use ClickHouseDB\Client;
use DI\Definition\Source\DefinitionArray;
use LukaLtaApi\App\Factory\ClickHouseFactory;
use LukaLtaApi\App\Factory\LoggerFactory;
use LukaLtaApi\App\Factory\MinIOFactory;
use LukaLtaApi\App\Factory\PdoFactory;
use LukaLtaApi\App\Factory\RedisFactory;
use LukaLtaApi\App\Factory\TelegramBotFactory;
use PDO;
use Psr\Log\LoggerInterface;
use Redis;
use TelegramBot\Api\BotApi;

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
            LoggerInterface::class => factory(LoggerFactory::class),
            PDO::class => factory(PdoFactory::class),
            Redis::class => factory(RedisFactory::class),
            BotApi::class => factory(TelegramBotFactory::class),
            S3Client::class => factory(MinIOFactory::class),
            Client::class => factory(ClickHouseFactory::class),
        ];
    }
}
