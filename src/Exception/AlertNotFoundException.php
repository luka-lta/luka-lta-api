<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

class AlertNotFoundException extends ApiException
{
    public function __construct(string $message = 'Alert not found.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
