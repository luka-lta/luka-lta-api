<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Business\Customer;
use LukaLtaApi\Value\Business\CustomerId;
use LukaLtaApi\Value\Business\Customers;
use PDO;
use PDOException;

class BusinessCustomerRepository
{
    private const string CUSTOMER_SELECT = <<<SQL
        SELECT
            c.customer_id,
            c.company_name,
            c.contact_name,
            c.street,
            c.postal_code,
            c.city,
            c.country,
            c.email,
            c.vat_id,
            c.notes,
            c.archived_at
        FROM business_customers c
    SQL;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function loadCustomer(CustomerId $customerId): ?Customer
    {
        $sql = self::CUSTOMER_SELECT . ' WHERE c.customer_id = :customer_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['customer_id' => $customerId->asString()]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch customer', previous: $exception);
        }

        return $row !== false ? Customer::fromDatabase($row) : null;
    }

    public function loadCustomers(bool $includeArchived): Customers
    {
        $sql = self::CUSTOMER_SELECT;
        if (!$includeArchived) {
            $sql .= ' WHERE c.archived_at IS NULL';
        }
        $sql .= ' ORDER BY COALESCE(c.company_name, c.contact_name) ASC, c.created_at ASC';

        try {
            $stmt = $this->pdo->query($sql);
            $customers = [];
            foreach ($stmt as $row) {
                $customers[] = Customer::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch customers', previous: $exception);
        }

        return Customers::from(...$customers);
    }

    public function create(Customer $customer): void
    {
        $sql = <<<SQL
            INSERT INTO business_customers
                (customer_id, company_name, contact_name, street, postal_code, city, country, email, vat_id, notes)
            VALUES
                (:customer_id, :company_name, :contact_name, :street, :postal_code, :city, :country, :email,
                 :vat_id, :notes)
        SQL;

        try {
            $this->pdo->prepare($sql)->execute($customer->toDatabaseRow());
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to create customer', previous: $exception);
        }
    }

    public function update(Customer $customer): void
    {
        $sql = <<<SQL
            UPDATE business_customers
            SET company_name = :company_name,
                contact_name = :contact_name,
                street = :street,
                postal_code = :postal_code,
                city = :city,
                country = :country,
                email = :email,
                vat_id = :vat_id,
                notes = :notes
            WHERE customer_id = :customer_id
        SQL;

        try {
            $this->pdo->prepare($sql)->execute($customer->toDatabaseRow());
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to update customer', previous: $exception);
        }
    }

    /** Idempotent: ein bereits archivierter Kunde behält seinen ursprünglichen Zeitpunkt */
    public function archive(CustomerId $customerId): void
    {
        $sql = 'UPDATE business_customers SET archived_at = NOW() WHERE customer_id = :customer_id'
            . ' AND archived_at IS NULL';

        try {
            $this->pdo->prepare($sql)->execute(['customer_id' => $customerId->asString()]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to archive customer', previous: $exception);
        }
    }
}
