<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Throwable;

class BusinessOfferNotFoundException extends ApiException
{
    public function __construct(string $message = 'Offer not found', ?Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
