<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\WebTracking\Tracking;

class UrlParameter
{
    private function __construct(
        private readonly array $parameters,
    ) {
    }

    public static function fromRawString(?string $queryString): self
    {
        if (!$queryString) {
            return new self([]);
        }

        $params = [];

        $clean = str_starts_with($queryString, '?')
            ? substr($queryString, 1)
            : $queryString;

        $parsed = [];
        parse_str($clean, $parsed);

        foreach ($parsed as $key => $value) {
            // Nur Strings, keine Arrays
            if (is_scalar($value)) {
                $params[strtolower((string)$key)] = (string)$value;
            }
        }

        return new self($params);
    }

    public static function from(array $parameters): self
    {
        return new self($parameters);
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
