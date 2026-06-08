<?php

declare(strict_types=1);

namespace LukaLtaApi\Service\Contracts;

interface PaginationServiceInterface
{
    public function getPaginationData(string $table, int $pageSize, array $where = []): array;
}
