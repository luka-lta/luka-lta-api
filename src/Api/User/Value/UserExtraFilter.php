<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\User\Value;

use LukaLtaApi\Value\AbstractDataTableFilterParameter;

class UserExtraFilter extends AbstractDataTableFilterParameter
{
    protected function getExtraFilterName(): array
    {
        return [
            'email',
            'created_at',
            'updated_at',
        ];
    }
}
