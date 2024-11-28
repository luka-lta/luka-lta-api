<?php

namespace LukaLtaApi\Service;

use LukaLtaApi\Value\LinkCollection\LinkId;
use LukaLtaApi\Value\LinkCollection\LinkItem;
use Redis;
use RedisException;

class LinkItemCachingService
{
    public const string HASH_KEY = 'link_items';

    public function __construct(
        private readonly Redis $redis,
    ) {
    }

    public function addItem(LinkItem $linkItem): void
    {
        try {
            $this->redis->hset(
                self::HASH_KEY,
                $linkItem->getLinkId()?->asInt(),
                json_encode($linkItem->toArray(), JSON_THROW_ON_ERROR)
            );
        } catch (RedisException) {
            // Do nothing
        }
    }

    public function deleteItem(LinkId $linkId): void
    {
        try {
            $this->redis->hdel(self::HASH_KEY, $linkId->asString());
        } catch (RedisException) {
            // Do nothing
        }
    }

    public function updateItem(LinkItem $item): bool
    {
        try {
            $existingItem = $this->getItem($item->getLinkId());
            if ($existingItem) {
                $this->addItem($item);
                return true;
            }
        } catch (RedisException) {
            // Do nothing
        }
        return false;
    }

    public function getItem(LinkId $linkId): ?LinkItem
    {
        try {
            $itemData = $this->redis->hget(self::HASH_KEY, $linkId->asInt());
            if ($itemData) {
                $dataArray = json_decode($itemData, true, 512, JSON_THROW_ON_ERROR);
                return LinkItem::fromDatabase($dataArray);
            }
        } catch (RedisException) {
            // Do nothing
        }
        return null;
    }

    public function getAllItems(): ?array
    {
        try {
            $items = $this->redis->hgetall(self::HASH_KEY);
        } catch (RedisException) {
            // Do nothing
        }

        if (!$items) {
            return null;
        }

        return array_map(
            static fn($item) => LinkItem::fromDatabase(json_decode($item, true, 512, JSON_THROW_ON_ERROR)),
            $items
        );
    }

    public function deleteAllItems(): void
    {
        try {
            $this->redis->del([self::HASH_KEY]);
        } catch (RedisException) {
            // Do nothing
        }
    }
}
