<?php

namespace LukaLtaApi\App\Factory;

use LukaLtaApi\Repository\EnvironmentRepository;
use Psr\Container\ContainerInterface;
use TelegramBot\Api\BotApi;

class TelegramBotFactory
{
    public function __invoke(ContainerInterface $container): BotApi
    {
        /** @var EnvironmentRepository $envRepo */
        $envRepo = $container->get(EnvironmentRepository::class);
        return new BotApi($envRepo->get('TELEGRAM_BOT_TOKEN'));
    }
}
