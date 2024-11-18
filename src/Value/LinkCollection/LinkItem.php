<?php

namespace LukaLtaApi\Value\LinkCollection;

class LinkItem
{
    private function __construct(
        private readonly ?int $id,
        private DisplayName   $displayname,
        private Description   $description,
        private LinkUrl       $url,
        private bool          $isActive,
        private IconName      $iconName
    ) {
    }

    public static function from(
        ?int        $id,
        DisplayName $displayname,
        Description $description,
        LinkUrl     $url,
        ?bool       $isActive,
        IconName    $iconName
    ): self
    {
        return new self($id, $displayname, $description, $url, $isActive ?? false, $iconName);
    }

    public static function fromDatabase(array $data): self
    {
        return new self(
            $data['link_id'] ?? null,
            DisplayName::fromString($data['displayname']),
            Description::fromString($data['description'] ?? null),
            LinkUrl::fromString($data['url']),
            $data['is_active'],
            IconName::fromString($data['icon_name'] ?? null)
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'displayname' => $this->displayname->__toString(),
            'description' => $this->description->getValue(),
            'url' => $this->url->__toString(),
            'isActive' => $this->isActive,
            'iconName' => $this->iconName->getValue(),
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getIconName(): IconName
    {
        return $this->iconName;
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
}
