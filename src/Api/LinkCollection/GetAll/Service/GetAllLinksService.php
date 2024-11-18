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

    public function getAllLinks(): ?array
    {
        $links = $this->repository->getAll();

        if ($links === null) {
            return null;
        }

        // Wandelt jedes LinkItem-Objekt in ein Array um
        return array_map(static fn(LinkItem $link) => $link->toArray(), $links);
    }
}
