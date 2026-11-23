<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Throwable;

class BusinessPdfRenderException extends ApiException
{
    public function __construct(string $message = 'Failed to render PDF', ?Throwable $previous = null)
    {
        parent::__construct($message, 500, $previous);
    }
}
