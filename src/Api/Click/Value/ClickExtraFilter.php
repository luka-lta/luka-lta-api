<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Click\Value;

use LukaLtaApi\Value\AbstractDataTableFilterParameter;

class ClickExtraFilter extends AbstractDataTableFilterParameter
{
    protected function getExtraFilterName(): array
    {
        return [
            'displayname',
            'url',
            'ip_address',
            'market',
            'user_agent',
            'referrer',
            'clicked_at'
        ];
    }
}
