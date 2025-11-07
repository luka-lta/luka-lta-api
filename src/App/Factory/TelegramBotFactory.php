<?php

namespace LukaLtaApi\App\Factory;

use TelegramBot\Api\BotApi;

class TelegramBotFactory
{
    public function __invoke(): BotApi
    {
        return new BotApi(getenv('TELEGRAM_BOT_TOKEN'));
    }
}
