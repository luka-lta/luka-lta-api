<?php

declare(strict_types=1);

namespace LukaLtaApi\Service;

use LukaLtaApi\Repository\PaginationRepository;

class PaginationService
{
    public function __construct(
        private readonly PaginationRepository $paginationRepository,
    ) {
    }

    /**
     * @return array {
     *     dataCount: int,
     *     totalPages: int,
     * }
     */
    public function getPaginationData(string $table, int $pageSize, array $where = []): array
    {
        $dataCount = $this->paginationRepository->getDataCount($table, $where);

        $totalPages = (int)ceil($dataCount / $pageSize);

        return [
            'dataCount' => $dataCount,
            'totalPages' => $totalPages,
        ];
    }

}
