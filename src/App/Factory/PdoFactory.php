<?php

namespace LukaLtaApi\App\Factory;

use LukaLtaApi\Repository\EnvironmentRepository;
use PDO;
use Psr\Container\ContainerInterface;

class PdoFactory
{
    private const string ENV_HOST     = 'MYSQL_HOST';
    private const string ENV_DATABASE = 'MYSQL_DATABASE';
    private const string ENV_USER     = 'MYSQL_USER';
    private const string ENV_PASSWORD = 'MYSQL_PASSWORD';
    private const string ENV_PORT     = 'MYSQL_PORT';
    private const array OPTIONS  = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];


    public function __invoke(ContainerInterface $container): PDO
    {
        /** @var EnvironmentRepository $envRepo */
        $envRepo = $container->get(EnvironmentRepository::class);

        $dsn = sprintf(
            'mysql:host=%s:%d;dbname=%s',
            $envRepo->get(self::ENV_HOST),
            $envRepo->get(self::ENV_PORT, '3306'),
            $envRepo->get(self::ENV_DATABASE),
        );

        return new PDO(
            $dsn,
            $envRepo->get(self::ENV_USER),
            $envRepo->get(self::ENV_PASSWORD),
            self::OPTIONS,
        );
    }
}
