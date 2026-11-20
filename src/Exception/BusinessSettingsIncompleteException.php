<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Throwable;

class BusinessSettingsIncompleteException extends ApiException
{
    public function __construct(string $message = 'Business settings are incomplete', ?Throwable $previous = null)
    {
        parent::__construct($message, 409, $previous);
    }
}
