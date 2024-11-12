<?php

use LukaLtaApi\App\Factory\ContainerFactory;
use LukaLtaApi\Slim\SlimFactory;

require __DIR__ . '/../vendor/autoload.php';

try {
    $container = ContainerFactory::build();
    $app = SlimFactory::create($container);
    $app->run();
} catch (Throwable $throwable) {
    echo $throwable->getMessage();
}
