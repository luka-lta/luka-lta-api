#!/usr/bin/env php
<?php

declare(strict_types=1);

use LukaLtaApi\App\Factory\ContainerFactory;
use LukaLtaApi\Command\CleanupSessionsCommand;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;

define('APP_ENV', getenv('APP_ENV'));

require_once __DIR__ . '/../vendor/autoload.php';


$container = ContainerFactory::build();
$logger = $container->get(LoggerInterface::class);
try {
    $application = new Application();

    $application->setCommandLoader(
        new ContainerCommandLoader($container, [
            CleanupSessionsCommand::COMMAND_NAME => CleanupSessionsCommand::class,
        ])
    );

    $application->run();
} catch (Throwable $exception) {
    $logger->critical('Uncaught exception in LukaLtaApi CLI', [
        'message' => $exception->getMessage(),
        'topic' => get_class($exception),
        'code' => $exception->getCode(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTrace(),
    ]);
    echo 'Error: ' . $exception->getMessage() . PHP_EOL;
}
