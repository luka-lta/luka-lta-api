<?php

namespace LukaLtaApi\Value\LinkCollection;

class LinkItem
{
    private function __construct(
        private readonly ?int        $id,
        private readonly DisplayName $displayname,
        private readonly Description $description,
        private readonly LinkUrl     $url,
        private readonly bool        $isActive,
        private readonly IconName $iconName
    ) {
    }

    public static function from(
        ?int $id,
        DisplayName $displayname,
        Description $description,
        LinkUrl $url,
        ?bool $isActive,
        IconName $iconName
    ): self {
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
}
