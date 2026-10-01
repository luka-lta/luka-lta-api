<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Homelab\Service\HomelabIngestService;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class IngestContainerMetricsAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator     $requestValidator,
        private readonly HomelabIngestService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->requestValidator->validate($request, [
            'containerId'       => ['required' => true, 'location' => 'body'],
            'hostId'            => ['required' => true, 'location' => 'body'],
            'name'              => ['required' => true, 'location' => 'body'],
            'image'             => ['required' => true, 'location' => 'body'],
            'status'            => ['required' => true, 'location' => 'body'],
            'healthStatus'      => ['required' => true, 'location' => 'body'],
            'cpuUsagePercent'   => ['required' => true, 'location' => 'body'],
            'memoryUsedMb'      => ['required' => true, 'location' => 'body'],
            'memoryLimitMb'     => ['required' => true, 'location' => 'body'],
            'networkInMbps'     => ['required' => true, 'location' => 'body'],
            'networkOutMbps'    => ['required' => true, 'location' => 'body'],
            'restartCount'      => ['required' => true, 'location' => 'body'],
        ]);

        $data = $request->getParsedBody();
        $data['startedAt']          ??= null;
        $data['lastHealthCheckAt']  ??= null;
        $data['ports']              ??= [];
        $data['volumes']            ??= [];
        $data['environment']        ??= [];

        return $this->service->ingestContainer($data)->getResponse($response);
    }
}
