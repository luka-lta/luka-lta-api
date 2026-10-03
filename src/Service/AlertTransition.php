<?php

declare(strict_types=1);

namespace LukaLtaApi\Service;

enum AlertTransition: string
{
    case Created = 'created';
    case Bumped = 'bumped';
    case Resolved = 'resolved';
}
