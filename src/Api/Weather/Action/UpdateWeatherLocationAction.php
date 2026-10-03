<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Weather\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Api\Weather\Service\WeatherService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UpdateWeatherLocationAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
        private readonly WeatherService   $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->requestValidator->validate($request, [
            'city' => ['required' => true, 'location' => 'body'],
            'latitude' => ['required' => true, 'location' => 'body'],
            'longitude' => ['required' => true, 'location' => 'body'],
        ]);

        return $this->service->updateLocation($request->getParsedBody())->getResponse($response);
    }
}
