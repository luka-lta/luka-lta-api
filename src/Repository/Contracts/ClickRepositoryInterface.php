<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository\Contracts;

use DateTimeImmutable;
use LukaLtaApi\Api\Click\Value\ClickExtraFilter;
use LukaLtaApi\Api\Click\Value\ClicksFilter;
use LukaLtaApi\Value\PaginatedData;
use LukaLtaApi\Value\Tracking\Click;
use LukaLtaApi\Value\Tracking\ClickSummary;

interface ClickRepositoryInterface
{
    public function recordClick(Click $click): void;

    public function getSummary(): ClickSummary;

    public function listStats(DateTimeImmutable $startDate, DateTimeImmutable $endDate): array;

    public function listAll(ClickExtraFilter $filter): PaginatedData;

    public function getFilters(): ClicksFilter;
}
