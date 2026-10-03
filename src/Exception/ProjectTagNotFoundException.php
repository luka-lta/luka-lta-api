<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Throwable;

class ProjectTagNotFoundException extends ApiException
{
    public function __construct(string $message = 'Project tag not found.', ?Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
