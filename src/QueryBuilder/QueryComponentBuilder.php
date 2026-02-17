<?php

declare(strict_types=1);

namespace LukaLtaApi\QueryBuilder;

use DateTime;
use LukaLtaApi\Value\WebTracking\Site\SiteMetricRequestData;

class QueryComponentBuilder
{
    public function buildTimeStatement(SiteMetricRequestData $metricRequestData): string
    {
        $pastMinutesStart = $metricRequestData->getPastMinutesStart();
        $pastMinutesEnd = $metricRequestData->getPastMinutesEnd();

        if ($pastMinutesStart !== null && $pastMinutesEnd !== null) {
            return $this->buildPastMinutesRange($pastMinutesStart, $pastMinutesEnd);
        }

        $startDate = $metricRequestData->getStartDate();
        $endDate = $metricRequestData->getEndDate();
        $timeZone = $metricRequestData->getTimeZone();

        if ($startDate && $endDate && $timeZone) {
            return $this->buildDateRange($startDate, $endDate, $timeZone);
        }

        return '';
    }

    private function buildPastMinutesRange(DateTime $start, DateTime $end): string
    {
        $now = new DateTime('now', new \DateTimeZone('UTC'));
        $startTimestamp = (clone $now)->modify("-{$start->format('i')} minutes")->format('Y-m-d H:i:s');
        $endTimestamp = (clone $now)->modify("-{$end->format('i')} minutes")->format('Y-m-d H:i:s');

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

    public function buildLimitStatement(SiteMetricRequestData $metricRequestData, bool $isCountQuery): string
    {
        if ($isCountQuery) {
            return '';
        }

        $limit = $metricRequestData->getLimit() ?? 100;
        return "LIMIT $limit";
    }

    public function buildOffsetStatement(SiteMetricRequestData $metricRequestData, bool $isCountQuery): string
    {
        if ($isCountQuery) {
            return '';
        }

        $page = $metricRequestData->getPage();
        if ($page === null || $page < 1) {
            return '';
        }

        $limit = $metricRequestData->getLimit() ?? 100;
        $offset = ($page - 1) * $limit;

        return "OFFSET $offset";
    }
}
