<?php

declare(strict_types=1);

namespace LukaLtaApi\Service\Contracts;

use LukaLtaApi\Value\LinkCollection\LinkId;
use LukaLtaApi\Value\LinkCollection\LinkItem;

interface LinkItemCachingServiceInterface
{
    public function addItem(LinkItem $linkItem): void;

    public function deleteItem(LinkId $linkId): void;

    public function updateItem(LinkItem $item): bool;

    public function getItem(LinkId $linkId): ?LinkItem;

    public function getAllItems(): ?array;

    public function deleteAllItems(): void;
}
