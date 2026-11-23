<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Customer\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Customer\Service\CustomerService;
use LukaLtaApi\Value\Business\CustomerId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X PATCH http://localhost/api/v1/customers/<uuid>
//        --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
//        --header 'Content-Type: application/json' --data '{"email":"max@example.tld"}'
class UpdateCustomerAction extends ApiAction
{
    public function __construct(
        private readonly CustomerService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $customerId = CustomerId::fromString((string) $request->getAttribute('customerId'));

        return $this->service
            ->updateCustomer($customerId, (array) $request->getParsedBody())
            ->getResponse($response);
    }
}
