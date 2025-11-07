<?php

namespace LukaLtaApi\Logger;

use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use TelegramBot\Api\BotApi;

class LoggerWrapper implements LoggerInterface
{
    public function __construct(
        private readonly Logger $logger,
        private readonly BotApi $botApi,
    ) {
    }

    private function shouldAlert(string $level): bool
    {
        return $level >= LogLevel::CRITICAL;
    }

    private function logAndAlert(string $level, string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);

        if ($this->shouldAlert($level)) {
            $this->botApi->sendMessage(getenv('TELEGRAM_CHAT_ID'), $message);
        }
    }

    public function emergency($message, array $context = []): void
    {
        $this->logAndAlert(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->logAndAlert(Logger::ALERT, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->logAndAlert(Logger::CRITICAL, $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->logAndAlert(Logger::ERROR, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->logAndAlert(Logger::WARNING, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $this->logAndAlert($level, $message, $context);
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }
}
