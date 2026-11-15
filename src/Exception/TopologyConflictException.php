<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Throwable;

class TopologyConflictException extends ApiException
{
    private function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 409, $previous);
    }

    public static function referenceAlreadyMapped(string $nodeName): self
    {
        return new self("Reference is already mapped by node \"{$nodeName}\"");
    }

    public static function edgeAlreadyExists(): self
    {
        return new self('Edge already exists');
    }
}
