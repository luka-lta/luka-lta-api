<?php

namespace LukaLtaApi\Api\LinkCollection\GetAll\Service;

use LukaLtaApi\Repository\LinkCollectionRepository;
use LukaLtaApi\Value\LinkCollection\LinkItem;

class GetAllLinksService
{
    public function __construct(
        private readonly LinkCollectionRepository $repository
    ) {
    }

    public function getAllLinks(bool $mustRef): ?array
    {
        $links = $this->repository->getAll();

        if ($links->count() === 0) {
            return null;
        }

        return $links->toArray($mustRef);
    }
}
