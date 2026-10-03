<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Calendar\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Calendar\Service\CalendarService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DeleteCalendarSourceAction extends ApiAction
{
    public function __construct(
        private readonly CalendarService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $sourceId = (int) $request->getAttribute('sourceId');

        return $this->service->deleteSource($sourceId)->getResponse($response);
    }
}
