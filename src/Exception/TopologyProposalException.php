<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Throwable;

class TopologyProposalException extends ApiException
{
    private function __construct(string $message, int $code, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function notFound(): self
    {
        return new self('Topology proposal not found', 404);
    }

    public static function alreadyReviewed(string $status): self
    {
        return new self("Topology proposal is already {$status}", 409);
    }
}
