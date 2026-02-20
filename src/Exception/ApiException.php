<?php

namespace LukaLtaApi\Exception;

use Exception;
use Throwable;

/** @SuppressWarnings(PHPMD.NumberOfChildren) */
class ApiException extends Exception
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        private readonly array $context = [],
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function hasContext(): bool
    {
        return !empty($this->context);
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
