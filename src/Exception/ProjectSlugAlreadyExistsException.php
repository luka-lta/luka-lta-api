<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Throwable;

class ProjectSlugAlreadyExistsException extends ApiException
{
    public function __construct(string $message = 'Project slug already exists.', ?Throwable $previous = null)
    {
        parent::__construct($message, 409, $previous);
    }
}
