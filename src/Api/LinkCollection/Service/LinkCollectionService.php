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

    public function getDetailLink(array $attributes, bool $isAuthenticated = false): ApiResult
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
        $link = $this->repository->findById($linkId, $isAuthenticated);

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
        $isAuthenticated = !empty($request->getHeaderLine('Authorization'));

        $links = $this->repository->getAll($filter, $isAuthenticated);

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
        $linkItem = LinkItem::from(
            null,
            ClickTag::fromRandom(),
            $body['displayname'],
            $body['description'] ?? null,
            $body['url'],
            $body['isActive'] ?? false,
            new DateTimeImmutable(),
            IconName::fromString($body['iconName']),
            $body['displayOrder'] ?? 0,
        );

        $createdLink = $this->repository->create($linkItem);

        return ApiResult::from(
            JsonResult::from('Link created', [
                'link' => $createdLink->toArray()
            ]),
            StatusCodeInterface::STATUS_CREATED
        );
    }

    public function deactivateLink(array $params): ApiResult
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
        $link = $this->repository->findById($linkId, true);

        if (!$link) {
            return ApiResult::from(
                JsonResult::from(
                    'Link not found'
                ),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        if ($link->isDeactivated()) {
            return ApiResult::from(
                JsonResult::from('Link is already deactivated'),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        $link->setDeactivated(true);
        $this->repository->setDeactivated($link->getLinkId(), true);

        return ApiResult::from(JsonResult::from('Link deactivated'));
    }

    public function activateLink(array $params): ApiResult
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
        $link = $this->repository->findById($linkId, true);

        if (!$link) {
            return ApiResult::from(
                JsonResult::from(
                    'Link not found'
                ),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        if (!$link->isDeactivated()) {
            return ApiResult::from(
                JsonResult::from('Link is already active'),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        $link->setDeactivated(false);
        $this->repository->setDeactivated($link->getLinkId(), false);

        return ApiResult::from(JsonResult::from('Link activated'));
    }

    public function deleteLink(array $params): ApiResult
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
        $link = $this->repository->findById($linkId, true);

        if (!$link) {
            return ApiResult::from(
                JsonResult::from(
                    'Link not found'
                ),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        $this->repository->delete($linkId);

        return ApiResult::from(
            JsonResult::from('Link deleted'),
            StatusCodeInterface::STATUS_NO_CONTENT
        );
    }

    public function editLink(ServerRequestInterface $request): ApiResult
    {
        $linkId = (int)$request->getAttribute('linkId');

        $linkItem = $this->repository->findById(LinkId::fromInt($linkId), true);

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
