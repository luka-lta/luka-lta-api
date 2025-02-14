<?php

namespace LukaLtaApi\Api\LinkCollection\Edit\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\LinkNotFoundException;
use LukaLtaApi\Repository\LinkCollectionRepository;
use LukaLtaApi\Value\LinkCollection\Description;
use LukaLtaApi\Value\LinkCollection\DisplayName;
use LukaLtaApi\Value\LinkCollection\IconName;
use LukaLtaApi\Value\LinkCollection\LinkId;
use LukaLtaApi\Value\LinkCollection\LinkItem;
use LukaLtaApi\Value\LinkCollection\LinkUrl;

class EditLinkService
{
    public function __construct(
        private readonly LinkCollectionRepository $repository,
    ) {
    }

    public function edit(
        int     $linkId,
        string  $displayname,
        ?string $description,
        string  $url,
        bool    $isActive,
        ?string $iconName
    ): LinkItem {
        $linkItem = $this->repository->getById(LinkId::fromInt($linkId));

        if ($linkItem === null) {
            throw new LinkNotFoundException('Link not found', StatusCodeInterface::STATUS_NOT_FOUND);
        }

        $linkMetaData = $linkItem->getMetaData();

        $linkMetaData->setDisplayName(DisplayName::fromString($displayname));
        $linkMetaData->setDescription(Description::fromString($description));
        $linkMetaData->setLinkUrl(LinkUrl::fromString($url));
        $linkMetaData->setIsActive($isActive);

        $linkItem->setIconName(IconName::fromString($iconName));

        $this->repository->update($linkItem);

        return $linkItem;
    }
}
