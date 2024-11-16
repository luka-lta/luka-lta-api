<?php

namespace LukaLtaApi\Api\LinkCollection\Create;

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
        ?string $iconName
    ): void {
        $this->repository->createNewLink(
            LinkItem::from(
                null,
                DisplayName::fromString($displayname),
                Description::fromString($description),
                LinkUrl::fromString($url),
                $isActive,
                IconName::fromString($iconName)
            )
        );
    }
}
