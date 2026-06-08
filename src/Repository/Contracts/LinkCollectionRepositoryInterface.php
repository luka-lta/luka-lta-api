<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository\Contracts;

use LukaLtaApi\Api\LinkCollection\Value\LinkTreeExtraFilter;
use LukaLtaApi\Value\LinkCollection\LinkId;
use LukaLtaApi\Value\LinkCollection\LinkItem;
use LukaLtaApi\Value\LinkCollection\LinkItems;
use LukaLtaApi\Value\Tracking\ClickTag;

interface LinkCollectionRepositoryInterface
{
    public function create(LinkItem $link): LinkItem;

    public function getByClickTag(ClickTag $tag): ?LinkItem;

    public function getById(LinkId $linkId): ?LinkItem;

    public function update(LinkItem $linkItem): LinkItem;

    public function disableLink(LinkId $linkId): void;

    public function getAll(LinkTreeExtraFilter $filter): LinkItems;
}
