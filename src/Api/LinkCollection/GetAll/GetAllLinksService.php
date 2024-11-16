<?php

namespace LukaLtaApi\Api\LinkCollection\GetAll;

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
        $links = $this->repository->getAllLinks();

        if ($links === null) {
            return null;
        }

        // Wandelt jedes LinkItem-Objekt in ein Array um
        return array_map(static fn(LinkItem $link) => $link->toArray(), $links);
    }
}
