<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\LinkCollection;

class LinkMetaData
{
    private function __construct(
        private DisplayName $displayName,
        private Description $description,
        private LinkUrl $linkUrl,
        private bool $isActive,
    ) {
    }

    public static function from(
        DisplayName $displayName,
        Description $description,
        LinkUrl $linkUrl,
        bool $isActive
    ): self {
        return new self($displayName, $description, $linkUrl, $isActive);
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            DisplayName::fromString($row['displayname']),
            Description::fromString($data['description'] ?? null),
            LinkUrl::fromString($row['link_url']),
            $row['is_active'],
        );
    }

    public function getDisplayName(): DisplayName
    {
        return $this->displayName;
    }

    public function getDescription(): Description
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getLinkUrl(): LinkUrl
    {
        return $this->linkUrl;
    }

    public function setDescription(Description $description): void
    {
        $this->description = $description;
    }

    public function setDisplayName(DisplayName $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function setLinkUrl(LinkUrl $linkUrl): void
    {
        $this->linkUrl = $linkUrl;
    }
}
