<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Click\Service;

use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\ClickRepository;
use LukaLtaApi\Repository\LinkCollectionRepository;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\Tracking\Click;
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
        $clickTag = ClickTag::fromString($request->getAttribute('clickTag'));
        $body = $request->getParsedBody();
        $ipAdress = $body['ipAdress'] ?? null;
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
            $ipAdress,
            $userAgent,
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
    public function getAllClicks(): ApiResult
    {
        $clicks = $this->repository->getAll();

        if ($clicks->count() === 0) {
            return ApiResult::from(
                JsonResult::from('No clicks found'),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        return ApiResult::from(JsonResult::from('Clicks found', ['clicks' => $clicks->toArray()]));
    }
}
