<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Throwable;

class ProjectAssetUploadException extends ApiException
{
    public function __construct(string $message, int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
