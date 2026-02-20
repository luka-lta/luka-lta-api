<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Stringable;

interface FormattableException
{
    public function format(): string | Stringable;

    public function formatHuman(): string | Stringable;
}
