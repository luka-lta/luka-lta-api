<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Throwable;

/** Fachlich unzulässiger Statuswechsel oder Bearbeitung eines Belegs (409) */
class BusinessDocumentStateException extends ApiException
{
    private function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 409, $previous);
    }

    public static function notDraft(): self
    {
        return new self('Document is not a draft');
    }

    public static function notSent(): self
    {
        return new self('Offer has not been sent');
    }

    public static function notAccepted(): self
    {
        return new self('Offer has not been accepted');
    }

    public static function notOpen(): self
    {
        return new self('Invoice is not open');
    }

    public static function notPaid(): self
    {
        return new self('Invoice is not paid');
    }

    public static function notCancellable(): self
    {
        return new self('Only open or paid invoices can be cancelled');
    }

    public static function cancellationNotCancellable(): self
    {
        return new self('A cancellation invoice cannot be cancelled');
    }

    public static function depositReferencedByFinal(): self
    {
        return new self('Deposit invoice is referenced by a final invoice');
    }

    public static function customerArchived(): self
    {
        return new self('Customer is archived');
    }
}
