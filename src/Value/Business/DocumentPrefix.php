<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

enum DocumentPrefix: string
{
    case Invoice = 'RE';
    case Offer = 'AN';

    /** Belegart für Fehlermeldungen der API (englisch) */
    public function getLabel(): string
    {
        return match ($this) {
            self::Invoice => 'Invoice',
            self::Offer => 'Offer',
        };
    }
}
