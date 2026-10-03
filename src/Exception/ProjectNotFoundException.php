<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Throwable;

class ProjectNotFoundException extends ApiException
{
    public function __construct(string $message = 'Project not found.', ?Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
