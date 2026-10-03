<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Calendar\Action;

use DateTimeImmutable;
use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Calendar\Service\CalendarService;
use LukaLtaApi\Exception\ApiValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetCalendarEventsAction extends ApiAction
{
    public function __construct(
        private readonly CalendarService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();

        if (!isset($params['from'], $params['to'])) {
            throw new ApiValidationException('from and to query parameters are required.', 400);
        }

        $from = new DateTimeImmutable($params['from']);
        $to = new DateTimeImmutable($params['to']);

        return $this->service->getEvents($from, $to)->getResponse($response);
    }
}
