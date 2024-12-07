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
        string $displayName,
        ?string $description,
        string $linkUrl,
        bool $isActive
    ): self {
        return new self(
            DisplayName::fromString($displayName),
            Description::fromString($description),
            LinkUrl::fromString($linkUrl),
            $isActive
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
