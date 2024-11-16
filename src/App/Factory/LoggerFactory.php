<?php

namespace LukaLtaApi\App\Factory;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LogLevel;

class LoggerFactory
{
    public function __invoke(): Logger
    {
        $logger = new Logger('ApiLogger');

        $streamHandler = new StreamHandler('/app/logs/', LogLevel::DEBUG);

        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            null,
            true,
            true
        );
        $streamHandler->setFormatter($formatter);

        $logger->pushHandler($streamHandler);

        return $logger;
    }
}
