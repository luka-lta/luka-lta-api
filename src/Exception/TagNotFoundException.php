<?php

namespace LukaLtaApi\Exception;

class TagNotFoundException extends ApiException
{
    public function __construct(string $message = 'Tag not found.', \Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
