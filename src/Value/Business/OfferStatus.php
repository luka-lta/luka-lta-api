<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

enum OfferStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    /** Nur ein versendetes Angebot kann angenommen oder abgelehnt werden */
    public function isDecision(): bool
    {
        return $this === self::Accepted || $this === self::Rejected;
    }
}
