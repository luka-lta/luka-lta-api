<?php

declare(strict_types=1);

namespace LukaLtaApi\Value;

use IteratorAggregate;

class PaginatedData
{
    public function __construct(
        private readonly int $total,
        private readonly int $totalPages,
        private readonly int $perPage,
        private readonly IteratorAggregate $data,
    ) {
    }

    public static function from(
        int $total,
        int $totalPages,
        int $perPage,
        IteratorAggregate $data
    ): self {
        return new self($total, $totalPages, $perPage, $data);
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'totalPages' => $this->totalPages,
            'perPage' => $this->perPage,
        ];
    }

    public function getData(): IteratorAggregate
    {
        return $this->data;
    }

}
