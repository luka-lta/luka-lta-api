<?php

namespace LukaLtaApi\Api\LinkCollection\GetAll\Service;

use LukaLtaApi\Repository\LinkCollectionRepository;
use LukaLtaApi\Service\FilterService;
use LukaLtaApi\Value\LinkCollection\LinkItem;

class GetAllLinksService
{
    public function __construct(
        private readonly LinkCollectionRepository $repository,
        private readonly FilterService $filterService,
    ) {
    }

    public function getAllLinks(bool $mustRef, array $filterOptions): ?array
    {
        $filterOptions = $this->checkForCorrectFilterParms($filterOptions);
        $filter = $this->filterService->filter('displayname', '=', 'test');

        $links = $this->repository->getAll($filter);

        if ($links === null) {
            return null;
        }

        // Wandelt jedes LinkItem-Objekt in ein Array um
        return array_map(static fn(LinkItem $link) => $link->toArray($mustRef), $links);
    }

    private function checkForCorrectFilterParms(array $queryParams): array
    {
        $params = ['displayname', 'description', 'url', 'is_active', 'icon_name', 'display_order'];

        foreach ($params as $param) {
            if (!array_key_exists($param, $queryParams)) {
                unset($queryParams[$param]);
            }
        }

        return $queryParams;
    }
}
