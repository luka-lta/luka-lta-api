<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\BusinessSettings\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\BusinessSettings\Service\BusinessSettingsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl http://localhost/api/v1/business/settings
//        --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
class GetBusinessSettingsAction extends ApiAction
{
    public function __construct(
        private readonly BusinessSettingsService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getSettings()->getResponse($response);
    }
}
