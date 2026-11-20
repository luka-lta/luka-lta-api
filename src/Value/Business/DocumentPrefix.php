<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

enum DocumentPrefix: string
{
    case Invoice = 'RE';
    case Offer = 'AN';
}
