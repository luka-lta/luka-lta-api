<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\BusinessSettings\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\BusinessSettings\Service\BusinessSettingsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X PUT http://localhost/api/v1/business/settings
//        --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
//        --header 'Content-Type: application/json' --data '{"companyName":"Muster GmbH","ownerName":"Max Muster",
//        "street":"Musterweg 1","postalCode":"12345","city":"Musterstadt","email":"info@example.tld",
//        "taxNumber":"12/345/67890","bankName":"Musterbank","iban":"DE02 1203 0000 0000 2020 51"}'
class UpdateBusinessSettingsAction extends ApiAction
{
    public function __construct(
        private readonly BusinessSettingsService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service
            ->updateSettings((array) $request->getParsedBody())
            ->getResponse($response);
    }
}
