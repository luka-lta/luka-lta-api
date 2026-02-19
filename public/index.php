<?php

use LukaLtaApi\App\Factory\ContainerFactory;
use LukaLtaApi\Exception\ApiException;
use LukaLtaApi\Exception\Formatter\ExceptionFormatter;
use LukaLtaApi\Slim\SlimFactory;
use Psr\Log\LoggerInterface;

require __DIR__ . '/../vendor/autoload.php';
$container = ContainerFactory::build();


try {
    $app = SlimFactory::create($container);
    $app->run();
} catch (Throwable $throwable) {
    define('STDOUT', fopen('php://stdout', 'wb'));
    define('STDERR', fopen('php://stderr', 'wb'));

    $logger = $container->get(LoggerInterface::class);
    $formatter = $container->get(ExceptionFormatter::class);

    $context = ['error' => $throwable];

    if ($throwable instanceof ApiException && $throwable->hasContext()) {
        $context = array_merge($context, $throwable->getContext());
    }

    $logger->emergency('Uncaught exception: ', $context);

    $formatter->formatException($throwable);
}
