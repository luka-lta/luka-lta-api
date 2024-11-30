<?php

namespace LukaLtaApi\Value\LinkCollection;

use DateTimeImmutable;

class LinkItem
{
    private function __construct(
        private readonly ?LinkId           $linkId,
        private DisplayName                $displayname,
        private Description                $description,
        private LinkUrl                    $url,
        private bool                       $isActive,
        private readonly DateTimeImmutable $createdOn,
        private IconName                   $iconName,
        private int                        $displayOrder,
    )
    {
    }

    public static function from(
        ?LinkId           $linkId,
        DisplayName       $displayname,
        Description       $description,
        LinkUrl           $url,
        ?bool             $isActive,
        DateTimeImmutable $createdOn,
        IconName          $iconName,
        int               $displayOrder,
    ): self
    {
        return new self(
            $linkId,
            $displayname,
            $description,
            $url,
            $isActive ?? false,
            $createdOn,
            $iconName,
            $displayOrder
        );
    }

    public static function fromDatabase(array $data): self
    {
        return new self(
            LinkId::fromInt($data['link_id']),
            DisplayName::fromString($data['displayname']),
            Description::fromString($data['description'] ?? null),
            LinkUrl::fromString($data['url']),
            $data['is_active'],
            new DateTimeImmutable($data['created_on']),
            IconName::fromString($data['icon_name'] ?? null),
            $data['display_order'],
        );
    }

    public function toArray(bool $mustRef = false): array
    {
        return [
            'id' => $this->linkId->asInt(),
            'displayname' => $this->displayname->__toString(),
            'description' => $this->description->getValue(),
            'url' => $mustRef ? $this->url->getAsTrackUrl() : $this->url->__toString(),
            'isActive' => $this->isActive,
            'createdOn' => $this->createdOn->format('Y-m-d H:i:s'),
            'iconName' => $this->iconName->getValue(),
            'displayOrder' => $this->displayOrder,
        ];
    }

    public function getLinkId(): ?LinkId
    {
        return $this->linkId;
    }

    public function getDisplayName(): DisplayName
    {
        return $this->displayname;
    }

    public function getDescription(): Description
    {
        return $this->description;
    }

    public function getUrl(): LinkUrl
    {
        return $this->url;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedOn(): DateTimeImmutable
    {
        return $this->createdOn;
    }

    public function getIconName(): IconName
    {
        return $this->iconName;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function setDisplayname(DisplayName $displayname): void
    {
        $this->displayname = $displayname;
    }

    public function setDescription(Description $description): void
    {
        $this->description = $description;
    }

    public function setUrl(LinkUrl $url): void
    {
        $this->url = $url;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function setIconName(IconName $iconName): void
    {
        $this->iconName = $iconName;
    }

    public function setDisplayOrder(int $displayOrder): void
    {
        $this->displayOrder = $displayOrder;
    }
}
