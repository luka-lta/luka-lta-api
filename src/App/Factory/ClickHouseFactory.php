<?php

namespace LukaLtaApi\App\Factory;

use ClickHouseDB\Client;

class ClickHouseFactory
{
    public function __invoke(): Client
    {
        $config = [
            'host' => getenv('CLICKHOUSE_HOST'),
            'port' => getenv('CLICKHOUSE_PORT'),
            'username' => getenv('CLICKHOUSE_USERNAME'),
            'password' => getenv('CLICKHOUSE_PASSWORD'),
            'https' => (bool)getenv('CLICKHOUSE_HTTPS'),
        ];

        return new Client($config);
    }
}
