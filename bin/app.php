#!/usr/bin/env php
<?php

declare(strict_types=1);

use LukaLtaApi\App\Factory\ContainerFactory;
use LukaLtaApi\Command\Alert\CleanupAlertsCommand;
use LukaLtaApi\Command\Calendar\SyncCalendarCommand;
use LukaLtaApi\Command\Homelab\CheckHomelabHeartbeatsCommand;
use LukaLtaApi\Command\Homelab\CleanupHomelabDataCommand;
use LukaLtaApi\Command\Project\ImportLegacyProjectsCommand;
use LukaLtaApi\Command\Weather\FetchWeatherCommand;
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
            'homelab:cleanup'          => CleanupHomelabDataCommand::class,
            'homelab:check-heartbeats' => CheckHomelabHeartbeatsCommand::class,
            'weather:fetch'            => FetchWeatherCommand::class,
            'calendar:sync'            => SyncCalendarCommand::class,
            'alerts:cleanup'           => CleanupAlertsCommand::class,
            'projects:import-legacy'   => ImportLegacyProjectsCommand::class,
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
