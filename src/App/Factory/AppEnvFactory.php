<?php

declare(strict_types=1);

namespace LukaLtaApi\App\Factory;

use LukaLtaApi\Repository\EnvironmentRepository;
use LukaLtaApi\Value\Misc\AppEnv;
use Psr\Container\ContainerInterface;

class AppEnvFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $environmentRepository = $container->get(EnvironmentRepository::class);
        $value = $environmentRepository->getEnvironmentVariable('APP_ENV');

        return AppEnv::create($value);
    }
}
