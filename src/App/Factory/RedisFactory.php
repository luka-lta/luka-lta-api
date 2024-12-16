<?php

namespace LukaLtaApi\App\Factory;

use Redis;

class RedisFactory
{
    public function __invoke(): Redis
    {
        $client = new Redis();

        $client->connect(getenv('REDIS_HOST'), getenv('REDIS_PORT'));
        $client->auth([
            'password' => getenv('REDIS_PASSWORD'),
        ]);

        return $client;
    }
}
