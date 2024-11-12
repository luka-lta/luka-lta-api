<?php

namespace LukaLtaApi;

use DI\Definition\Source\DefinitionArray;
use LukaLtaApi\App\Factory\LoggerFactory;
use Monolog\Logger;
use PDO;
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
            PDO::class => factory(PDO::class),
        ];
    }
}
