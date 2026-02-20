<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Tracking;

use LukaLtaApi\Value\Identifier\AbstractId;

class ClickTag extends AbstractId
{
    protected const int LENGTH = 8;

    public function getAsTracking(): string
    {
        return 'https://luka-lta.dev/redirect/' . $this->asString();
    }
}
