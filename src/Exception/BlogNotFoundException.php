<?php

namespace LukaLtaApi\Exception;

class BlogNotFoundException extends ApiException
{
    public function __construct(string $message = 'Blog post not found.', \Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
