<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Calendar\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Calendar\Service\CalendarService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetCalendarSummaryAction extends ApiAction
{
    public function __construct(
        private readonly CalendarService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getSummary()->getResponse($response);
    }
}
