<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Customer\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Customer\Service\CustomerService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl 'http://localhost/api/v1/customers?includeArchived=1'
//        --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
class ListCustomersAction extends ApiAction
{
    public function __construct(
        private readonly CustomerService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $includeArchived = ($request->getQueryParams()['includeArchived'] ?? '') === '1';

        return $this->service->listCustomers($includeArchived)->getResponse($response);
    }
}
