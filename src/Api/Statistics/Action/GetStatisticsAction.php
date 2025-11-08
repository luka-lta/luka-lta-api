<?php

namespace LukaLtaApi\Api\Statistics\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Api\Statistics\Service\StatisticsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetStatisticsAction extends ApiAction
{
    public function __construct(
        private readonly StatisticsService $service,
        private readonly RequestValidator $validator,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = [
            'statistic' => ['required' => true, 'location' => RequestValidator::LOCATION_QUERY],
        ];

        $this->validator->validate($request, $rules);

        return $this->service->getStatistics($request)->getResponse($response);
    }
}
