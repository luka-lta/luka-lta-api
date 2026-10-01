<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

class HostNotFoundException extends ApiException
{
    public function __construct(string $message = 'Host not found.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
