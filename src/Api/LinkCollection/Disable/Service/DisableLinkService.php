<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\LinkCollection\Disable\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\LinkNotFoundException;
use LukaLtaApi\Repository\LinkCollectionRepository;
use LukaLtaApi\Value\LinkCollection\LinkId;

class DisableLinkService
{
    public function __construct(
        private readonly LinkCollectionRepository $repository,
    ) {
    }

    public function disableLink(LinkId $linkId): void
    {
        $existsLink = $this->repository->getById($linkId);

        if (!$existsLink) {
            throw new LinkNotFoundException('Link not found', StatusCodeInterface::STATUS_NOT_FOUND);
        }

        $this->repository->disableLink($existsLink->getLinkId());
    }
}
