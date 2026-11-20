<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Invoice\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Invoice\Service\InvoiceService;
use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Value\Business\CustomerId;
use LukaLtaApi\Value\Business\InvoiceStatus;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl 'http://localhost/api/v1/invoices?status=open&customerId=<uuid>&year=2026&overdue=1'
//        --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
class ListInvoicesAction extends ApiAction
{
    public function __construct(
        private readonly InvoiceService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();

        return $this->service
            ->listInvoices(
                $this->parseStatus($query['status'] ?? null),
                $this->parseCustomerId($query['customerId'] ?? null),
                $this->parseYear($query['year'] ?? null),
                $this->parseOverdue($query['overdue'] ?? null),
            )
            ->getResponse($response);
    }

    private function parseStatus(mixed $value): ?InvoiceStatus
    {
        if ($value === null || $value === '') {
            return null;
        }

        $status = is_string($value) ? InvoiceStatus::tryFrom($value) : null;
        if ($status === null) {
            throw new ApiValidationException('status must be one of draft, open, paid, cancelled, issued', 400);
        }

        return $status;
    }

    private function parseCustomerId(mixed $value): ?CustomerId
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CustomerId::fromString(is_string($value) ? $value : '');
    }

    private function parseYear(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || preg_match('/^\d{4}$/', $value) !== 1) {
            throw new ApiValidationException('year must have 4 digits', 400);
        }

        return (int) $value;
    }

    private function parseOverdue(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === '0') {
            return false;
        }
        if ($value !== '1') {
            throw new ApiValidationException('overdue must be 0 or 1', 400);
        }

        return true;
    }
}
