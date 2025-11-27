<?php

namespace LukaLtaApi\Api\WebTracking\TrackEvent\Service;

use LukaLtaApi\Queue\PageviewQueue;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\WebTracking\Tracking\PageViewEvent;
use Psr\Http\Message\ServerRequestInterface;

class TrackEventService
{
    public function __construct(
        private readonly PageviewQueue $pageviewQueue,
    ) {
    }

    public function trackEvent(ServerRequestInterface $request): ApiResult
    {

        $pageViewEvent = PageViewEvent::fromPayload($request->getParsedBody());
        $this->pageviewQueue->add($pageViewEvent);

        return ApiResult::from(JsonResult::from('OK'));
    }
}