<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\ApiKey\Value;

use LukaLtaApi\Value\AbstractDataTableFilterParameter;

class ApiKeyExtraFilter extends AbstractDataTableFilterParameter
{
    protected function getExtraFilterName(): array
    {
        return [
            'origin',
            'creator',
            'created_at',
            'expires_at',
        ];
    }
}
