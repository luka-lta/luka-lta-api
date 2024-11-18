<?php

namespace LukaLtaApi\App\Factory;

use Redis;

class RedisFactory
{
    public function __invoke(): Redis
    {
        $client = new Redis();

        $client->connect('redis-luka-lta');
        $client->auth([
            'password' => '1234',
        ]);

        return $client;
    }
}
