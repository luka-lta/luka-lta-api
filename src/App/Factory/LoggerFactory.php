<?php

namespace LukaLtaApi\App\Factory;

use LukaLtaApi\Logger\LoggerWrapper;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use TelegramBot\Api\BotApi;

class LoggerFactory
{
    public function __construct(
        private readonly BotApi $botApi
    ) {
    }

    public function __invoke(): LoggerInterface
    {
        $logFilePath = getenv('LOG_FILE_PATH') ?: '/app/logs';
        $logLevel = getenv('LOG_LEVEL') ?: LogLevel::DEBUG;

        $logger = new Logger('ApiLogger');

        $rotatingHandler = new RotatingFileHandler($logFilePath, 3, $logLevel);
        $rotatingHandler->setFormatter(new JsonFormatter());

        $logger->pushHandler($rotatingHandler);

        return new LoggerWrapper($logger, $this->botApi);
    }
}
