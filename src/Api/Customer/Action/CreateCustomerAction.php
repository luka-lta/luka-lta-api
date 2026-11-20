<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Customer\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Customer\Service\CustomerService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X POST http://localhost/api/v1/customers --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
//        --header 'Content-Type: application/json'
//        --data '{"companyName":"Muster GmbH","street":"Musterweg 1","postalCode":"12345","city":"Musterstadt"}'
class CreateCustomerAction extends ApiAction
{
    public function __construct(
        private readonly CustomerService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->createCustomer((array) $request->getParsedBody())->getResponse($response);
    }
}
