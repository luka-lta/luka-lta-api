<?php

namespace LukaLtaApi\App\Factory;

use Redis;

class RedisFactory
{
    public function __invoke(): Redis
    {
        $client = new Redis();

        $client->auth('1234');
        $client->connect('redis-luka-lta');

        return $client;
    }
}
