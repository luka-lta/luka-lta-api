<?php

namespace LukaLtaApi\App\Factory;

use PDO;

class PdoFactory
{
    public function __invoke(): PDO
    {
        $host = getenv('MYSQL_HOST');
        $port = 3306;
        $database = getenv('MYSQL_DATABASE');
        $username = getenv('MYSQL_USER');
        $password = getenv('MYSQL_PASSWORD');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $host, $port, $database);
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        return $pdo;
    }
}
