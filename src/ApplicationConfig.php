<?php

namespace LukaLtaApi;

use DI\Definition\Source\DefinitionArray;
use LukaLtaApi\App\Factory\LoggerFactory;
use LukaLtaApi\App\Factory\PdoFactory;
use LukaLtaApi\App\Factory\RedisFactory;
use Monolog\Logger;
use PDO;
use Psr\Log\LoggerInterface;
use Redis;
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
            Logger::class => factory(LoggerFactory::class),
            PDO::class => factory(PdoFactory::class),
            Redis::class => factory(RedisFactory::class),
        ];
    }
}
