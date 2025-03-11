<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\LinkCollection\Service;

use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\LinkCollectionRepository;
use LukaLtaApi\Value\LinkCollection\Description;
use LukaLtaApi\Value\LinkCollection\DisplayName;
use LukaLtaApi\Value\LinkCollection\IconName;
use LukaLtaApi\Value\LinkCollection\LinkId;
use LukaLtaApi\Value\LinkCollection\LinkItem;
use LukaLtaApi\Value\LinkCollection\LinkUrl;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ServerRequestInterface;

class LinkCollectionService
{
    public function __construct(
        private readonly LinkCollectionRepository $repository,
    ) {
    }

    public function getDetailLink(array $attributes): ApiResult
    {
        if (!isset($attributes['linkId'])) {
            return ApiResult::from(
                JsonResult::from(
                    'Link ID not found'
                ),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        $linkId = LinkId::fromString($attributes['linkId']);
        $link = $this->repository->getById($linkId);

        if ($link === null) {
            return ApiResult::from(
                JsonResult::from(
                    'Link not found'
                ),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        return ApiResult::from(JsonResult::from('Link found', ['link' => $link->toArray()]));
    }

    public function getAllLinks(ServerRequestInterface $request): ApiResult
    {
        $mustRef = $request->getQueryParams()['mustRef'] ?? false;

        $links = $this->repository->getAll($mustRef);

        if ($links->count() === 0) {
            return ApiResult::from(
                JsonResult::from('No links found'),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        return ApiResult::from(JsonResult::from('Links fetched successfully', ['links' => $links->toArray()]));
    }

    public function createLink(ServerRequestInterface $request): ApiResult
    {
        $body = $request->getParsedBody();

        $createdLink = $this->repository->create(
            LinkItem::from(
                null,
                $body['displayname'],
                $body['description'] ?? null,
                $body['url'],
                $body['isActive'] ?? false,
                new DateTimeImmutable(),
                IconName::fromString($body['iconName']),
                $body['displayOrder'] ?? 0,
            )
        );

        return ApiResult::from(
            JsonResult::from('Link created', [
                'link' => $createdLink->toArray()
            ]),
            StatusCodeInterface::STATUS_CREATED
        );
    }

    public function disableLink(array $params): ApiResult
    {
        if (!isset($params['linkId'])) {
            return ApiResult::from(
                JsonResult::from(
                    'Link ID not found'
                ),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        $linkId = LinkId::fromString($params['linkId']);
        $link = $this->repository->getById($linkId);

        if (!$link) {
            return ApiResult::from(
                JsonResult::from(
                    'Link not found'
                ),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        $link->setDeactivated(true);
        $this->repository->update($link);

        return ApiResult::from(JsonResult::from('Link disabled'));
    }

    public function editLink(ServerRequestInterface $request): ApiResult
    {
        $linkId = (int) $request->getAttribute('linkId');

        $linkItem = $this->repository->getById(LinkId::fromInt($linkId));

        if (!$linkItem) {
            return ApiResult::from(
                JsonResult::from('Link not found'),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        $this->checkUserHasChanges($linkItem, $request->getParsedBody());

        $this->repository->update($linkItem);

        return ApiResult::from(JsonResult::from('Link edited', ['link' => $linkItem->toArray()]));
    }

    private function checkUserHasChanges(LinkItem $linkItem, array $parsedBody): void
    {
        $linkMetaData = $linkItem->getMetaData();

        if (isset($parsedBody['displayname'])) {
            $linkMetaData->setDisplayName(DisplayName::fromString($parsedBody['displayname']));
        }

        if (array_key_exists('description', $parsedBody)) {
            $linkMetaData->setDescription(Description::fromString($parsedBody['description'] ?? null));
        }

        if (isset($parsedBody['url'])) {
            $linkMetaData->setLinkUrl(LinkUrl::fromString($parsedBody['url']));
        }

        if (isset($parsedBody['isActive'])) {
            $linkMetaData->setIsActive((bool) $parsedBody['isActive']);
        }

        if (array_key_exists('iconName', $parsedBody)) {
            $linkItem->setIconName(IconName::fromString($parsedBody['iconName'] ?? null));
        }
    }
}
