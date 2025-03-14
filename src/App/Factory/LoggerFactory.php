<?php

namespace LukaLtaApi\App\Factory;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\WebProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class LoggerFactory
{
    public function __invoke(): Logger
    {
        $logFilePath = getenv('LOG_FILE_PATH') ?: '/app/logs';
        $logLevel = getenv('LOG_LEVEL') ?: LogLevel::DEBUG;

        $logger = new Logger('ApiLogger');

        $rotatingHandler = new RotatingFileHandler($logFilePath, 7, $logLevel);
        $rotatingHandler->setFormatter(new JsonFormatter());

        $logger->pushProcessor(new WebProcessor());

        $logger->pushHandler($rotatingHandler);

        return $logger;
    }
}
