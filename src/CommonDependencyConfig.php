<?php

declare(strict_types=1);

namespace LukaLtaApi;

use DI\Definition\Source\DefinitionArray;
use LukaLtaApi\App\Factory\AppEnvFactory;
use LukaLtaApi\App\Factory\LoggerFactory;
use LukaLtaApi\Value\Misc\AppEnv;
use Psr\Log\LoggerInterface;

use function DI\factory;

class CommonDependencyConfig extends DefinitionArray
{
    public function __construct()
    {
        parent::__construct([
            LoggerInterface::class => factory(LoggerFactory::class),
            AppEnv::class => factory(AppEnvFactory::class),
        ]);
    }
}
