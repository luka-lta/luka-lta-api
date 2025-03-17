<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\LinkCollection\Value;

use LukaLtaApi\Value\AbstractDataTableFilterParameter;

class LinkTreeExtraFilter extends AbstractDataTableFilterParameter
{
    protected function getExtraFilterName(): array
    {
        return [
            'display_name',
            'is_active',
            'created_at',
        ];
    }
}
