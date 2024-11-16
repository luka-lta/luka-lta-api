<?php

namespace LukaLtaApi\Exception;

use Fig\Http\Message\StatusCodeInterface;
use Throwable;

class ApiDatabaseException extends ApiException
{
    public function __construct(
        string $message,
        int $code = StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
