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
use LukaLtaApi\Value\Tracking\Clicks;
use LukaLtaApi\Value\Tracking\ClickTag;
use LukaLtaApi\Value\Tracking\UserAgent;
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
        $clickTag = ClickTag::fromString($request->getAttribute('clickTag'));
        $body = $request->getParsedBody();
        $market = $body['market'] ?? null;
        $ipAddress = $body['ipAddress'] ?? null;
        $userAgent = $body['userAgent'] ?? null;
        $referer = $body['referrer'] ?? null;

        $linkItem = $this->linkCollectionRepository->getByClickTag($clickTag);

        if ($linkItem === null) {
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
            $ipAddress,
            $market,
            UserAgent::fromUserAgent($userAgent),
            $referer
        );

        $this->repository->recordClick($click);

        return ApiResult::from(
            JsonResult::from('Click tracked', [
                'redirectUrl' => (string)$click->getUrl(),
            ]),
            StatusCodeInterface::STATUS_FOUND
        );
    }

    public function getClicksStats(ServerRequestInterface $request): ApiResult
    {
        $startDate = new DateTimeImmutable($request->getQueryParams()['startDate'] ?? 'now');
        $endDate = new DateTimeImmutable($request->getQueryParams()['endDate'] ?? 'now');

        $clicks = $this->repository->listStats($startDate, $endDate);

        if (empty($clicks)) {
            return ApiResult::from(
                JsonResult::from('No clicks found', ['clicks' => []]),
            );
        }

        return ApiResult::from(JsonResult::from('Clicks found', ['clicks' => $clicks]));
    }

    public function getAllClicks(ServerRequestInterface $request): ApiResult
    {
        $filter = ClickExtraFilter::parseFromQuery($request->getQueryParams());
        $clicks = $this->repository->listAll($filter);
        /** @var Clicks $clicksData */
        $clicksData = $clicks->getData();

        if ($clicksData->count() === 0) {
            return ApiResult::from(
                JsonResult::from('No clicks found', ['clicks' => []]),
            );
        }

        return ApiResult::from(JsonResult::from('Clicks found', ['clicks' => $clicksData->toFrontend()]));
    }

    public function getClickSummary(): ApiResult
    {
        $summary = $this->repository->getSummary();

        return ApiResult::from(JsonResult::from('Summary found', ['summary' => $summary->toArray()]));
    }

    public function getFilters(): ApiResult
    {
        $filters = $this->repository->getFilters();

        return ApiResult::from(JsonResult::from('Filters found', ['filters' => $filters->toArray()]));
    }
}
