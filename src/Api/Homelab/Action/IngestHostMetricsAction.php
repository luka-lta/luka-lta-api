<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Homelab\Service\HomelabIngestService;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class IngestHostMetricsAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator     $requestValidator,
        private readonly HomelabIngestService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->requestValidator->validate($request, [
            'hostId'             => ['required' => true, 'location' => 'body'],
            'name'               => ['required' => true, 'location' => 'body'],
            'cpuUsagePercent'    => ['required' => true, 'location' => 'body'],
            'memoryUsedGb'       => ['required' => true, 'location' => 'body'],
            'memoryTotalGb'      => ['required' => true, 'location' => 'body'],
            'diskUsedGb'         => ['required' => true, 'location' => 'body'],
            'diskTotalGb'        => ['required' => true, 'location' => 'body'],
            'loadAverage'        => ['required' => true, 'location' => 'body'],
            'uptimeSeconds'      => ['required' => true, 'location' => 'body'],
        ]);

        $data = $request->getParsedBody();
        $data['temperatureCelsius'] ??= null;

        return $this->service->ingestHost($data)->getResponse($response);
    }
}
