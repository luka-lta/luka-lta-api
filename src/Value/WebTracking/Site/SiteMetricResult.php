<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\WebTracking\Site;

class SiteMetricResult
{
    private function __construct(
        private readonly array $metricResult,
        private readonly int $totalCount,
    ) {
    }

    public static function fromResult(array $dataResult, array $countResult) : self
    {
        return new self(
            $dataResult,
            count($countResult) > 0 ? (int)$countResult[0] : 0,
        );
    }

    private function processResults(array $results): array
    {
        $data = $results;

        foreach ($data as &$row) {
            foreach ($row as $key => &$value) {
                if (
                    $key !== "session_id" &&
                    $key !== "user_id" &&
                    $value !== null &&
                    $value !== "" &&
                    $value !== true &&
                    $value !== false &&
                    is_numeric($value) &&
                    is_string($value) // Nur konvertieren wenn es ein String ist
                ) {
                    // Convert to int or float depending on the value
                    $value = str_contains($value, '.') ? (float)$value : (int)$value;
                }
            }
        }

        return $data;
    }

    public function getMetricResult(): array
    {
        return $this->processResults($this->metricResult);
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }
}
