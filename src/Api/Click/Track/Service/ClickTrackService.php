<?php

namespace LukaLtaApi\Api\Click\Track\Service;

use LukaLtaApi\Repository\ClickRepository;
use LukaLtaApi\Value\Tracking\Click;

class ClickTrackService
{
    public function __construct(
        private readonly ClickRepository $repository
    ) {
    }

    public function track(Click $click): void
    {
        $this->repository->create($click);
    }
}
