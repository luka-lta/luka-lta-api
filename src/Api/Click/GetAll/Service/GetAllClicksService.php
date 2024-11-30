<?php

namespace LukaLtaApi\Api\Click\GetAll\Service;

use LukaLtaApi\Repository\ClickRepository;
use LukaLtaApi\Value\Tracking\Click;

class GetAllClicksService
{
    public function __construct(
        private readonly ClickRepository $repository,
    ) {
    }

    public function getAllClicks(): ?array
    {
        $clicks = $this->repository->getAll();

        if ($clicks === null) {
            return null;
        }

        return array_map(static fn(Click $click) => $click->toArray(), $clicks);
    }
}
