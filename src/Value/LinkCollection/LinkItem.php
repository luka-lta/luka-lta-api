<?php

namespace LukaLtaApi\Value\LinkCollection;

use DateTimeImmutable;
use LukaLtaApi\Value\Tracking\ClickTag;

class LinkItem
{
    private function __construct(
        private ?LinkId                    $linkId,
        private readonly ClickTag          $clickTag,
        private LinkMetaData               $metaData,
        private readonly DateTimeImmutable $createdOn,
        private IconName                   $iconName,
        private int                        $displayOrder,
        private bool                       $deactivated,
        private ?DateTimeImmutable         $deactivatedOn,
    ) {
    }

    public static function from(
        ?LinkId           $linkId,
        ClickTag          $clickTag,
        string       $displayname,
        string       $description,
        string           $url,
        ?bool             $isActive,
        DateTimeImmutable $createdOn,
        IconName          $iconName,
        int               $displayOrder,
    ): self {
        return new self(
            $linkId,
            $clickTag,
            LinkMetaData::from(
                $displayname,
                $description,
                $url,
                $isActive ?? false,
            ),
            $createdOn,
            $iconName,
            $displayOrder,
            false,
            null,
        );
    }

    public static function fromDatabase(array $data): self
    {
        return new self(
            LinkId::fromInt($data['link_id']),
            ClickTag::fromString($data['click_tag']),
            LinkMetaData::from(
                $data['displayname'],
                $data['description'],
                $data['url'],
                $data['is_active'],
            ),
            new DateTimeImmutable($data['created_at']),
            IconName::fromString($data['icon_name'] ?? null),
            $data['display_order'],
            $data['deactivated'],
            isset($data['deactivated_at']) ? new DateTimeImmutable($data['deactivated_at']) : null,
        );
    }

    public function toArray(bool $mustRef = false): array
    {
        return [
            'id' => $this->linkId?->asInt(),
            'clickTag' => $this->clickTag->getValue(),
            'displayname' => (string)$this->metaData->getDisplayName(),
            'description' => $this->metaData->getDescription()->getValue(),
            'url' => $mustRef ? $this->clickTag->getAsTracking() : (string)$this->metaData->getLinkUrl(),
            'isActive' => $this->metaData->isActive(),
            'createdOn' => $this->createdOn->format('Y-m-d H:i:s'),
            'iconName' => $this->iconName->getValue(),
            'displayOrder' => $this->displayOrder,
            'deactivated' => $this->deactivated,
            'deactivatedOn' => $this->deactivatedOn?->format('Y-m-d H:i:s'),
        ];
    }

    public function update(array $data): void
    {
        if (isset($data['displayname'])) {
            $this->metaData->setDisplayName($data['displayname']);
        }
        if (isset($data['description'])) {
            $this->metaData->setDescription($data['description']);
        }
        if (isset($data['url'])) {
            $this->metaData->setLinkUrl($data['url']);
        }
        if (isset($data['isActive'])) {
            $this->metaData->setIsActive((bool)$data['isActive']);
        }
        if (isset($data['iconName'])) {
            $this->iconName = IconName::fromString($data['iconName']);
        }
        if (isset($data['displayOrder'])) {
            $this->displayOrder = (int)$data['displayOrder'];
        }
    }

    public function getLinkId(): ?LinkId
    {
        return $this->linkId;
    }

    public function getClickTag(): ClickTag
    {
        return $this->clickTag;
    }

    public function getMetaData(): LinkMetaData
    {
        return $this->metaData;
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

    public function isDeactivated(): bool
    {
        return $this->deactivated;
    }

    public function getDeactivatedOn(): ?DateTimeImmutable
    {
        return $this->deactivatedOn;
    }

    public function setLinkId(?LinkId $linkId): void
    {
        $this->linkId = $linkId;
    }

    public function setMetaData(LinkMetaData $metaData): void
    {
        $this->metaData = $metaData;
    }

    public function setIconName(IconName $iconName): void
    {
        $this->iconName = $iconName;
    }

    public function setDisplayOrder(int $displayOrder): void
    {
        $this->displayOrder = $displayOrder;
    }

    public function setDeactivated(bool $deactivated): void
    {
        $this->deactivated = $deactivated;
    }

    public function setDeactivatedOn(?DateTimeImmutable $deactivatedOn): void
    {
        $this->deactivatedOn = $deactivatedOn;
    }
}
