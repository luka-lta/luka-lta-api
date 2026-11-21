<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Customer\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\BusinessCustomerNotFoundException;
use LukaLtaApi\Repository\BusinessCustomerRepository;
use LukaLtaApi\Value\Business\Customer;
use LukaLtaApi\Value\Business\CustomerId;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;

class CustomerService
{
    public function __construct(
        private readonly BusinessCustomerRepository $repository,
    ) {
    }

    public function listCustomers(bool $includeArchived): ApiResult
    {
        $customers = $this->repository->loadCustomers($includeArchived);

        return ApiResult::from(JsonResult::from('Customers fetched', ['customers' => $customers->toArray()]));
    }

    public function getCustomer(CustomerId $customerId): ApiResult
    {
        $customer = $this->loadCustomerOrFail($customerId);

        return ApiResult::from(JsonResult::from('Customer fetched', ['customer' => $customer->toArray()]));
    }

    /** @param array<string, mixed> $data */
    public function createCustomer(array $data): ApiResult
    {
        $customer = Customer::fromRequest($data);
        $this->repository->create($customer);

        $created = $this->loadCustomerOrFail($customer->getCustomerId());

        return ApiResult::from(
            JsonResult::from('Customer created', ['customer' => $created->toArray()]),
            StatusCodeInterface::STATUS_CREATED,
        );
    }

    /** @param array<string, mixed> $data nur die zu ändernden Felder */
    public function updateCustomer(CustomerId $customerId, array $data): ApiResult
    {
        $existing = $this->loadCustomerOrFail($customerId);
        $customer = Customer::fromRequest(array_replace($existing->toArray(), $data), $customerId);
        $this->repository->update($customer);

        return $this->getCustomer($customerId);
    }

    public function archiveCustomer(CustomerId $customerId): ApiResult
    {
        $this->loadCustomerOrFail($customerId);
        $this->repository->archive($customerId);

        return $this->getCustomer($customerId);
    }

    private function loadCustomerOrFail(CustomerId $customerId): Customer
    {
        $customer = $this->repository->loadCustomer($customerId);
        if ($customer === null) {
            throw new BusinessCustomerNotFoundException();
        }

        return $customer;
    }
}
