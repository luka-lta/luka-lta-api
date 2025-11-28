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
        $data = $request->getParsedBody();

        $data['user_agent']  = $request->getHeader('User-Agent')[0] ?? null;
        $data['ip_address']  = $request->getHeader('X-Forwarded-For')[0] ?? $request->getServerParam('REMOTE_ADDR');

        $pageViewEvent = PageViewEvent::fromPayload($data);
        $this->pageviewQueue->add($pageViewEvent);

        return ApiResult::from(JsonResult::from('OK'));
    }
}
