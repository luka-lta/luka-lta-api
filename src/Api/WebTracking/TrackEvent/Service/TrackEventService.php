<?php

namespace LukaLtaApi\Api\WebTracking\TrackEvent\Service;

use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\WebTracking\Tracking\PageViewEvent;
use Psr\Http\Message\ServerRequestInterface;

class TrackEventService
{
    public function __construct(
        private readonly EventHandleService $handleService,
    ) {
    }

    public function trackEvent(ServerRequestInterface $request): ApiResult
    {
        $data = $request->getParsedBody();

        $data['userAgent']  = $request->getHeader('User-Agent')[0] ?? null;
        $data['ipAddress']  = $request->getHeader('X-Forwarded-For')[0] ?? $request->getServerParam('REMOTE_ADDR');

        $pageViewEvent = PageViewEvent::fromPayload($data);

        $this->handleService->handleEvent($pageViewEvent);

        return ApiResult::from(JsonResult::from('OK'));
    }
}
