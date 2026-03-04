<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Request;

use DateTime;
use DateTimeZone;
use LukaLtaApi\Value\Tracking\MetricParameter;

class RequestQueryParams
{
    private function __construct(
        private ?DateTime $startDate,
        private ?DateTime $endDate,
        private readonly ?DateTimeZone $timeZone,
        private ?DateTime $pastMinutesStart,
        private ?DateTime $pastMinutesEnd,
        private readonly ?int $page,
        private readonly ?int $limit,
        private readonly ?MetricParameter $parameter,
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
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : null;
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : null;
        $parameter = isset($queryParams['parameter']) ? MetricParameter::fromName($queryParams['parameter']) : null;

        return new self(
            $startDate,
            $endDate,
            $timeZone,
            $pastMinutesStart,
            $pastMinutesEnd,
            $page,
            $limit,
            $parameter
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

    public function getParameter(): ?MetricParameter
    {
        return $this->parameter;
    }
}
