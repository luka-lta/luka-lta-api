<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

class ApiKeyNotFoundException extends ApiException
{
    public function __construct(string $message = 'API key not found.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
