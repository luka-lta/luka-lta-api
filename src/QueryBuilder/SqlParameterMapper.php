<?php

declare(strict_types=1);

namespace LukaLtaApi\QueryBuilder;

use LukaLtaApi\Value\Tracking\MetricParameter;

class SqlParameterMapper
{
    public function map(MetricParameter $parameter): string
    {
        $value = $parameter->value;

        if (str_starts_with($value, 'utm_') || str_starts_with($value, 'url_param:')) {
            return $this->mapUrlParameter($value);
        }

        return match ($parameter) {
            MetricParameter::REFERRER => 'domainWithoutWWW(referrer)',
            MetricParameter::ENTRY_PAGE => "(SELECT pathname FROM events e2 WHERE e2.session_id = events.session_id ORDER BY occurred_on ASC LIMIT 1)",
            MetricParameter::EXIT_PAGE => "(SELECT pathname FROM events e2 WHERE e2.session_id = events.session_id ORDER BY occurred_on DESC LIMIT 1)",
            MetricParameter::DIMENSIONS => "CONCAT(CAST(screen_width AS CHAR), 'x', CAST(screen_height AS CHAR))",
            MetricParameter::CITY => "CONCAT(CAST(region AS CHAR), '-', CAST(city AS CHAR))",
            MetricParameter::BROWSER_VERSION => "CONCAT(CAST(browser AS CHAR), ' ', CAST(browser_version AS CHAR))",
            MetricParameter::OS_VERSION => "CASE WHEN CONCAT(CAST(os AS CHAR), ' ', CAST(os_version AS CHAR)) = 'Windows 10' THEN 'Windows 10/11' ELSE CONCAT(CAST(os AS CHAR), ' ', CAST(os_version AS CHAR)) END",
            default => $value
        };
    }

    private function mapUrlParameter(string $value): string
    {
        if (str_starts_with($value, 'url_param:')) {
            $paramName = substr($value, strlen('url_param:'));
            return "url_parameters->>'$.{$paramName}'";
        }

        return "url_parameters->>'$.{$value}'";
    }
}
