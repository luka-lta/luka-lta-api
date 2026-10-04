<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Throwable;

class ProjectTagSlugConflictException extends ApiException
{
    public function __construct(string $message = 'Project tag slug conflict.', ?Throwable $previous = null)
    {
        parent::__construct($message, 409, $previous);
    }
}
