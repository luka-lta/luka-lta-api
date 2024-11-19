<?php

namespace LukaLtaApi\Api\Click\Track\Service;

use LukaLtaApi\Repository\ClickRepository;
use LukaLtaApi\Value\Tracking\UrlClick;

class ClickTrackService
{
    public function __construct(
        private readonly ClickRepository $repository
    )
    {
    }

    public function track(UrlClick $click): void
    {
        $this->repository->create($click);
    }
}
