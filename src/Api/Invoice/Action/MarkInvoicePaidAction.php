<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Invoice\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Invoice\Service\InvoiceService;
use LukaLtaApi\Value\Business\InvoiceId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X POST http://localhost/api/v1/invoices/<uuid>/payment
//        --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
//        --header 'Content-Type: application/json' --data '{"paidAt":"2026-11-20"}'
class MarkInvoicePaidAction extends ApiAction
{
    public function __construct(
        private readonly InvoiceService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $invoiceId = InvoiceId::fromString((string) $request->getAttribute('invoiceId'));

        return $this->service
            ->markPaid($invoiceId, (array) $request->getParsedBody())
            ->getResponse($response);
    }
}
