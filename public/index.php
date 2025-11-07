<?php

use LukaLtaApi\App\Factory\ContainerFactory;
use LukaLtaApi\Slim\SlimFactory;
use Psr\Log\LoggerInterface;

require __DIR__ . '/../vendor/autoload.php';
$container = ContainerFactory::build();

$logger = $container->get(LoggerInterface::class);

try {
    $app = SlimFactory::create($container);
    $app->run();
} catch (Throwable $throwable) {
    $logger->emergency('Uncaught exception: ' . $throwable->getMessage(), [
        'topic' => $throwable::class,
        'message' => $throwable->getMessage(),
        'file' => $throwable->getFile(),
        'line' => $throwable->getLine(),
        'trace' => $throwable->getTrace(),
    ]);
}
