<?php

namespace LukaLtaApi\Api\LinkCollection\Create\Service;

use DateTimeImmutable;
use LukaLtaApi\Repository\LinkCollectionRepository;
use LukaLtaApi\Value\LinkCollection\Description;
use LukaLtaApi\Value\LinkCollection\DisplayName;
use LukaLtaApi\Value\LinkCollection\IconName;
use LukaLtaApi\Value\LinkCollection\LinkItem;
use LukaLtaApi\Value\LinkCollection\LinkUrl;

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
                DisplayName::fromString($displayname),
                Description::fromString($description),
                LinkUrl::fromString($url),
                $isActive,
                new DateTimeImmutable(),
                IconName::fromString($iconName),
                $displayOrder,
            )
        );
    }
}
