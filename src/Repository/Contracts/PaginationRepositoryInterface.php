<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository\Contracts;

interface PaginationRepositoryInterface
{
    public function getDataCount(string $table, array $where = []): int;
}
