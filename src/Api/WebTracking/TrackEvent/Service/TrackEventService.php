<?php

namespace LukaLtaApi\Api\WebTracking\TrackEvent\Service;

use LukaLtaApi\Queue\PageviewQueue;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ServerRequestInterface;

class TrackEventService
{
    public function __construct(
        private readonly PageviewQueue $pageviewQueue,
    ) {
    }

    public function trackEvent(ServerRequestInterface $request): ApiResult
    {
        $this->pageviewQueue->add($request->getParsedBody());

        return ApiResult::from(JsonResult::from('OK'));
    }
}