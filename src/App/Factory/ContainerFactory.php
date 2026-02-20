<?php

namespace LukaLtaApi\App\Factory;

use DI\Container;
use DI\ContainerBuilder;
use LukaLtaApi\ApplicationConfig;
use LukaLtaApi\CommonDependencyConfig;

class ContainerFactory
{
    public static function build(): Container
    {
        $container = new ContainerBuilder();
        $container->useAutowiring(true);
        $container->addDefinitions(
            new CommonDependencyConfig(),
            new ApplicationConfig(),
        );
        return  $container->build();
    }
}
