<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Tracking;

use DateTime;
use DateTimeZone;

class MetricRequestData
{
    private function __construct(
        private ?DateTime $startDate,
        private ?DateTime $endDate,
        private readonly ?DateTimeZone $timeZone,
        private ?DateTime $pastMinutesStart,
        private ?DateTime $pastMinutesEnd,
        private readonly ?int $page,
        private readonly ?int $limit,
        private readonly MetricParameter $metricParameter,
    ) {
    }

    public static function fromQueryParams(
        array $queryParams
    ): self {
        $startDate = isset($queryParams['startDate']) ? new DateTime($queryParams['startDate']) : null;
        $endDate = isset($queryParams['endDate']) ? new DateTime($queryParams['endDate']) : null;
        $pastMinutesStart = isset($queryParams['pastMinutesStart']) ? new DateTime($queryParams['pastMinutesStart']) : null;
        $pastMinutesEnd = isset($queryParams['pastMinutesEnd']) ? new DateTime($queryParams['pastMinutesEnd']) : null;
        $timeZone = isset($queryParams['timeZone']) ? new DateTimeZone($queryParams['timeZone']) : new DateTimeZone('UTC');

        return new self(
            $startDate,
            $endDate,
            $timeZone,
            $pastMinutesStart,
            $pastMinutesEnd,
            $queryParams['page'] ?? null,
            $queryParams['limit'] ?? null,
            MetricParameter::fromName($queryParams['parameter'])
        );
    }

    public function getStartDate(): ?DateTime
    {
        return $this->startDate;
    }

    public function getEndDate(): ?DateTime
    {
        return $this->endDate;
    }

    public function getTimeZone(): ?DateTimeZone
    {
        return $this->timeZone;
    }

    public function getPastMinutesEnd(): ?DateTime
    {
        return $this->pastMinutesEnd;
    }

    public function getPastMinutesStart(): ?DateTime
    {
        return $this->pastMinutesStart;
    }

    public function getPage(): ?int
    {
        return $this->page;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getMetricParameter(): MetricParameter
    {
        return $this->metricParameter;
    }
}
