<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Business\Service;

use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Exception\BusinessCustomerNotFoundException;
use LukaLtaApi\Exception\BusinessDocumentStateException;
use LukaLtaApi\Repository\BusinessCustomerRepository;
use LukaLtaApi\Value\Business\Customer;
use LukaLtaApi\Value\Business\CustomerId;

/** Kundenauflösung für Belege (Angebote, Rechnungen) */
class BusinessCustomerLookup
{
    public function __construct(
        private readonly BusinessCustomerRepository $customerRepository,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function parseCustomerId(array $data): CustomerId
    {
        $customerId = $data['customerId'] ?? null;
        if (!is_string($customerId)) {
            throw new ApiValidationException('customerId is required', 400);
        }

        return CustomerId::fromString($customerId);
    }

    public function loadCustomerOrFail(CustomerId $customerId): Customer
    {
        $customer = $this->customerRepository->loadCustomer($customerId);
        if ($customer === null) {
            throw new BusinessCustomerNotFoundException();
        }

        return $customer;
    }

    /** Neue Belege und Kundenwechsel nur für nicht archivierte Kunden */
    public function loadActiveCustomerOrFail(CustomerId $customerId): Customer
    {
        $customer = $this->loadCustomerOrFail($customerId);
        if ($customer->isArchived()) {
            throw BusinessDocumentStateException::customerArchived();
        }

        return $customer;
    }
}
