<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Weather\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Weather\Service\WeatherService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetWeatherSummaryAction extends ApiAction
{
    public function __construct(
        private readonly WeatherService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getSummary()->getResponse($response);
    }
}
