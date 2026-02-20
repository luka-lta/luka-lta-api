<?php

namespace LukaLtaApi\App\Factory;

use LukaLtaApi\Repository\EnvironmentRepository;
use Psr\Container\ContainerInterface;
use Redis;

class RedisFactory
{
    public function __invoke(ContainerInterface $container): Redis
    {
        /** @var EnvironmentRepository $envRepo */
        $envRepo = $container->get(EnvironmentRepository::class);
        $client = new Redis();

        $client->connect($envRepo->get('REDIS_HOST'), $envRepo->get('REDIS_PORT'));
        $client->auth([
            'password' => $envRepo->get('REDIS_PASSWORD'),
        ]);

        return $client;
    }
}
