<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Click\Service;

use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Api\Click\Value\ClickExtraFilter;
use LukaLtaApi\Repository\ClickRepository;
use LukaLtaApi\Repository\LinkCollectionRepository;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\Tracking\Click;
use LukaLtaApi\Value\Tracking\ClickMetadata;
use LukaLtaApi\Value\Tracking\Clicks;
use LukaLtaApi\Value\Tracking\ClickTag;
use Psr\Http\Message\ServerRequestInterface;

class ClickService
{
    public function __construct(
        private readonly ClickRepository          $repository,
        private readonly LinkCollectionRepository $linkCollectionRepository,
    ) {
    }

    public function track(ServerRequestInterface $request): ApiResult
    {
        $body = $request->getParsedBody();
        $clickTag = ClickTag::from($request->getAttribute('clickTag'));
        $clickMetaData = ClickMetadata::fromArray($body);

        $linkItem = $this->linkCollectionRepository->getByClickTag($clickTag);

        if ($linkItem === null || $linkItem->isDeactivated()) {
            return ApiResult::from(
                JsonResult::from('Invalid click tag'),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        $click = Click::from(
            null,
            $clickTag,
            $linkItem->getMetaData()->getLinkUrl(),
            new DateTimeImmutable(),
            $clickMetaData,
        );

        $this->repository->recordClick($click);

        return ApiResult::from(
            JsonResult::from('Click tracked', [
                'redirectUrl' => (string)$click->getUrl(),
            ]),
            StatusCodeInterface::STATUS_FOUND
        );
    }

    public function getAllClicks(ServerRequestInterface $request): ApiResult
    {
        $filter = ClickExtraFilter::parseFromQuery($request->getQueryParams());
        $clicks = $this->repository->getAll($filter);
        /** @var Clicks $clicksData */
        $clicksData = $clicks->getData();

        if ($clicksData->count() === 0) {
            return ApiResult::from(
                JsonResult::from('No clicks found', ['clicks' => []]),
            );
        }

        return ApiResult::from(JsonResult::from('Clicks found', ['clicks' => $clicksData->toFrontend()]));
    }
}
