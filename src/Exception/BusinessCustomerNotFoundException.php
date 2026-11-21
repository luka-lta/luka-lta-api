<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Throwable;

class BusinessCustomerNotFoundException extends ApiException
{
    public function __construct(string $message = 'Customer not found', ?Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
