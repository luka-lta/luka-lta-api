<?php

namespace LukaLtaApi\Api\LinkCollection\Create\Service;

use DateTimeImmutable;
use LukaLtaApi\Repository\LinkCollectionRepository;
use LukaLtaApi\Value\LinkCollection\IconName;
use LukaLtaApi\Value\LinkCollection\LinkItem;

class CreateLinkService
{
    public function __construct(
        private readonly LinkCollectionRepository $repository
    ) {
    }

    public function create(
        string $displayname,
        ?string $description,
        string $url,
        bool $isActive,
        ?string $iconName,
        int $displayOrder,
    ): void {
        $this->repository->create(
            LinkItem::from(
                null,
                $displayname,
                $description,
                $url,
                $isActive,
                new DateTimeImmutable(),
                IconName::fromString($iconName),
                $displayOrder,
            )
        );
    }
}
