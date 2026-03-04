<?php

declare(strict_types=1);

namespace LukaLtaApi\QueryBuilder;

use DateTime;
use LukaLtaApi\Value\Request\RequestQueryParams;

class QueryComponentBuilder
{
    public function buildTimeStatement(RequestQueryParams $requestQueryParams): string
    {
        $pastMinutesStart = $requestQueryParams->getPastMinutesStart();
        $pastMinutesEnd = $requestQueryParams->getPastMinutesEnd();

        if ($pastMinutesStart !== null && $pastMinutesEnd !== null) {
            return $this->buildPastMinutesRange($pastMinutesStart, $pastMinutesEnd);
        }

        $startDate = $requestQueryParams->getStartDate();
        $endDate = $requestQueryParams->getEndDate();
        $timeZone = $requestQueryParams->getTimeZone();

        if ($startDate && $endDate && $timeZone) {
            return $this->buildDateRange($startDate, $endDate, $timeZone);
        }

        return '';
    }

    private function buildPastMinutesRange(DateTime $start, DateTime $end): string
    {
        $now = new DateTime('now', new \DateTimeZone('UTC'));
        $startMinutes = max(0, (int) floor(($now->getTimestamp() - $start->getTimestamp()) / 60));
        $endMinutes = max(0, (int) floor(($now->getTimestamp() - $end->getTimestamp()) / 60));
        $startTimestamp = (clone $now)->modify("-{$startMinutes} minutes")->format('Y-m-d H:i:s');
        $endTimestamp = (clone $now)->modify("-{$endMinutes} minutes")->format('Y-m-d H:i:s');

        return "AND occurred_on > '$startTimestamp' AND occurred_on <= '$endTimestamp'";
    }

    private function buildDateRange(DateTime $startDate, DateTime $endDate, \DateTimeZone $timeZone): string
    {
        $startUtc = (clone $startDate)->setTime(0, 0)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $now = new DateTime('now', $timeZone);

        $endUtc  = (clone $endDate)
            ->setTime(0, 0)
            ->modify('+1 day')
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        if ($endDate->format('Y-m-d') === $now->format('Y-m-d')) {
            $endUtc = (new DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        }

        return "AND occurred_on >= '$startUtc' AND occurred_on < '$endUtc'";
    }

    public function buildLimitStatement(RequestQueryParams $requestQueryParams, bool $isCountQuery): string
    {
        if ($isCountQuery) {
            return '';
        }

        $limit = $requestQueryParams->getLimit() ?? 100;
        return "LIMIT $limit";
    }

    public function buildOffsetStatement(RequestQueryParams $requestQueryParams, bool $isCountQuery): string
    {
        if ($isCountQuery) {
            return '';
        }

        $page = $requestQueryParams->getPage();
        if ($page === null || $page < 1) {
            return '';
        }

        $limit = $requestQueryParams->getLimit() ?? 100;
        $offset = ($page - 1) * $limit;

        return "OFFSET $offset";
    }
}
