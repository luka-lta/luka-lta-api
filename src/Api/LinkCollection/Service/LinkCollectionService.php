<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\LinkCollection\Service;

use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Api\LinkCollection\Value\LinkTreeExtraFilter;
use LukaLtaApi\Repository\LinkCollectionRepository;
use LukaLtaApi\Value\LinkCollection\Description;
use LukaLtaApi\Value\LinkCollection\DisplayName;
use LukaLtaApi\Value\LinkCollection\IconName;
use LukaLtaApi\Value\LinkCollection\LinkId;
use LukaLtaApi\Value\LinkCollection\LinkItem;
use LukaLtaApi\Value\LinkCollection\LinkUrl;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\Tracking\ClickTag;
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
            );
        }

        return ApiResult::from(JsonResult::from('Link found', ['link' => $link->toArray()]));
    }

    public function getAllLinks(ServerRequestInterface $request): ApiResult
    {
        $filter = LinkTreeExtraFilter::parseFromQuery($request->getQueryParams());
        $mustRef = filter_var(
            $request->getQueryParams()['mustRef'] ?? false,
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        ) ?? false;

        $links = $this->repository->getAll($filter);

        if ($links->count() === 0) {
            return ApiResult::from(
                JsonResult::from('No links found', ['links' => []]),
            );
        }

        return ApiResult::from(
            JsonResult::from('Links fetched successfully', [
                'links' => $links->toArray($mustRef)
            ])
        );
    }

    public function createLink(ServerRequestInterface $request): ApiResult
    {
        $body = $request->getParsedBody();

        $createdLink = $this->repository->create(
            LinkItem::from(
                null,
                ClickTag::generateTag(),
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
        $this->repository->disableLink($link->getLinkId());

        return ApiResult::from(JsonResult::from('Link disabled'));
    }

    public function editLink(ServerRequestInterface $request): ApiResult
    {
        $linkId = (int)$request->getAttribute('linkId');

        $linkItem = $this->repository->getById(LinkId::fromInt($linkId));

        if (!$linkItem) {
            return ApiResult::from(
                JsonResult::from('Link not found')
            );
        }

        $linkItem->update($request->getParsedBody());

        $this->repository->update($linkItem);

        return ApiResult::from(JsonResult::from('Link edited', ['link' => $linkItem->toArray()]));
    }
}
